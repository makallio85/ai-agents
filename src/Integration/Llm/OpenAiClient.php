<?php
declare(strict_types=1);

namespace App\Integration\Llm;

use App\Integration\Llm\Tool\ToolCall;
use App\Integration\Llm\Tool\ToolCallResponse;
use App\Integration\Llm\Tool\ToolDefinition;
use RuntimeException;

/**
 * LLM client for the OpenAI Chat Completions API.
 *
 * Implements both blocking (complete) and streaming (stream) modes using
 * PHP's native curl extension. The streaming mode uses CURLOPT_WRITEFUNCTION
 * to process Server-Sent Events line by line as they arrive, invoking the
 * $onChunk callback with each text delta. No external SDK is required.
 *
 * Configuration is read from environment variables:
 *   OPENAI_API_KEY  — required, the OpenAI secret key
 *
 * The model is passed per-call via LlmService using the agent's llm_model
 * field (e.g. "gpt-4o", "gpt-4-turbo").
 */
class OpenAiClient implements LlmClientInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     */
    public function complete(array $messages, array $options = []): LlmResponse
    {
        $model = (string)($options['model'] ?? 'gpt-4o');
        $payload = $this->buildPayload($messages, $model, $options, false);

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode OpenAI request payload as JSON');
        }

        $curl = $this->createCurl();
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $body = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $httpCode !== 200) {
            throw new RuntimeException("OpenAI API error (HTTP {$httpCode}): " . ($body ?: 'empty response'));
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string)$body, true);
        $content = (string)($data['choices'][0]['message']['content'] ?? '');
        $tokensUsed = isset($data['usage']['total_tokens']) ? (int)$data['usage']['total_tokens'] : null;
        $finishReason = (string)($data['choices'][0]['finish_reason'] ?? 'stop');

        return new LlmResponse($content, $tokensUsed, $model, $finishReason);
    }

    /**
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     * @param callable(string): void $onChunk
     */
    public function stream(array $messages, array $options, callable $onChunk): LlmResponse
    {
        $model = (string)($options['model'] ?? 'gpt-4o');
        $payload = $this->buildPayload($messages, $model, $options, true);

        $assembled = '';

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode OpenAI streaming payload as JSON');
        }

        $curl = $this->createCurl();
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($ch, string $chunk) use (&$assembled, $onChunk): int {
            foreach (explode("\n", $chunk) as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }
                $jsonStr = substr($line, 6);
                if ($jsonStr === '[DONE]') {
                    continue;
                }
                /** @var array<string, mixed>|null $event */
                $event = json_decode($jsonStr, true);
                $delta = (string)($event['choices'][0]['delta']['content'] ?? '');
                if ($delta !== '') {
                    $assembled .= $delta;
                    $onChunk($delta);
                }
            }
            return strlen($chunk);
        });

        curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new RuntimeException("OpenAI streaming API error (HTTP {$httpCode})");
        }

        return new LlmResponse($assembled, null, $model, 'stop');
    }

    /**
     * Sends messages and tool definitions to the LLM and returns either a
     * final text response or a list of tool calls to execute.
     *
     * Used exclusively by AgentLoopService. Each call is non-streaming because
     * the loop needs the full structured response (including tool_call ids)
     * before it can dispatch tool execution.
     *
     * @param LlmMessage[] $messages Full conversation history including tool results from previous turns.
     * @param ToolDefinition[] $tools Available tools offered to the LLM.
     * @param array<string, mixed> $options Provider overrides (model, temperature, etc.).
     */
    public function completeWithTools(array $messages, array $tools, array $options = []): ToolCallResponse
    {
        $model = (string)($options['model'] ?? 'gpt-4o');
        $payload = $this->buildPayload($messages, $model, $options, false);
        $payload['tools'] = array_map(fn(ToolDefinition $t) => $t->toArray(), $tools);
        $payload['tool_choice'] = 'auto';

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode OpenAI tool-calling payload as JSON');
        }

        $curl = $this->createCurl();
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $body = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $httpCode !== 200) {
            throw new RuntimeException("OpenAI tool-calling API error (HTTP {$httpCode}): " . ($body ?: 'empty response'));
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string)$body, true);
        $tokensUsed = isset($data['usage']['total_tokens']) ? (int)$data['usage']['total_tokens'] : null;
        $finishReason = (string)($data['choices'][0]['finish_reason'] ?? 'stop');
        $message = $data['choices'][0]['message'] ?? [];

        // LLM wants to call one or more tools
        if ($finishReason === 'tool_calls' && !empty($message['tool_calls'])) {
            $toolCalls = [];
            foreach ($message['tool_calls'] as $tc) {
                $args = json_decode((string)($tc['function']['arguments'] ?? '{}'), true);
                $toolCalls[] = new ToolCall(
                    id: (string)($tc['id'] ?? ''),
                    name: (string)($tc['function']['name'] ?? ''),
                    arguments: is_array($args) ? $args : [],
                );
            }
            return new ToolCallResponse('', $toolCalls, $model, $tokensUsed);
        }

        // LLM produced a final text answer
        $content = (string)($message['content'] ?? '');
        return new ToolCallResponse($content, [], $model, $tokensUsed);
    }

    /**
     * Builds the JSON payload for the Chat Completions API.
     *
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, string $model, array $options, bool $stream): array
    {
        $payload = [
            'model' => $model,
            'messages' => array_map(fn(LlmMessage $m) => $m->toArray(), $messages),
            'stream' => $stream,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        }

        return $payload;
    }

    /** @return \CurlHandle */
    private function createCurl(): \CurlHandle
    {
        $curl = curl_init(self::API_URL);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialise curl');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        return $curl;
    }
}
