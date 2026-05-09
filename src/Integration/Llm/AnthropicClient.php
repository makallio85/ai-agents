<?php
declare(strict_types=1);

namespace App\Integration\Llm;

use RuntimeException;

/**
 * LLM client for the Anthropic Messages API.
 *
 * Implements both blocking (complete) and streaming (stream) modes using
 * PHP's native curl extension. Anthropic's API separates the system prompt
 * from the message history: the first message with role "system" is extracted
 * from the messages array and placed in the top-level "system" field as
 * required by the API contract.
 *
 * Streaming uses Server-Sent Events; content deltas are delivered in
 * "content_block_delta" events with type "text_delta".
 *
 * Configuration is read from environment variables:
 *   ANTHROPIC_API_KEY  — required, the Anthropic secret key
 *
 * The model is passed per-call via LlmService (e.g. "claude-sonnet-4-6").
 */
class AnthropicClient implements LlmClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     */
    public function complete(array $messages, array $options = []): LlmResponse
    {
        $model = (string)($options['model'] ?? 'claude-sonnet-4-6');
        [$system, $chatMessages] = $this->splitSystemMessage($messages);
        $payload = $this->buildPayload($chatMessages, $model, $system, $options, false);

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode Anthropic request payload as JSON');
        }

        $curl = $this->createCurl();
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $body = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $httpCode !== 200) {
            throw new RuntimeException("Anthropic API error (HTTP {$httpCode}): " . ($body ?: 'empty response'));
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string)$body, true);
        $content = (string)($data['content'][0]['text'] ?? '');
        $tokensUsed = isset($data['usage']['input_tokens'], $data['usage']['output_tokens'])
            ? ((int)$data['usage']['input_tokens'] + (int)$data['usage']['output_tokens'])
            : null;
        $finishReason = (string)($data['stop_reason'] ?? 'end_turn');

        return new LlmResponse($content, $tokensUsed, $model, $finishReason);
    }

    /**
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     * @param callable(string): void $onChunk
     */
    public function stream(array $messages, array $options, callable $onChunk): LlmResponse
    {
        $model = (string)($options['model'] ?? 'claude-sonnet-4-6');
        [$system, $chatMessages] = $this->splitSystemMessage($messages);
        $payload = $this->buildPayload($chatMessages, $model, $system, $options, true);

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode Anthropic streaming payload as JSON');
        }

        $assembled = '';

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
                /** @var array<string, mixed>|null $event */
                $event = json_decode($jsonStr, true);
                if (!is_array($event)) {
                    continue;
                }
                if (($event['type'] ?? '') === 'content_block_delta' && ($event['delta']['type'] ?? '') === 'text_delta') {
                    $delta = (string)($event['delta']['text'] ?? '');
                    if ($delta !== '') {
                        $assembled .= $delta;
                        $onChunk($delta);
                    }
                }
            }
            return strlen($chunk);
        });

        curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            throw new RuntimeException("Anthropic streaming API error (HTTP {$httpCode})");
        }

        return new LlmResponse($assembled, null, $model, 'end_turn');
    }

    /**
     * Extracts the system message from the messages array.
     * Anthropic requires the system prompt in a dedicated top-level field,
     * not as a message with role "system".
     *
     * @param LlmMessage[] $messages
     * @return array{0: string|null, 1: LlmMessage[]}
     */
    private function splitSystemMessage(array $messages): array
    {
        $system = null;
        $chatMessages = [];
        foreach ($messages as $msg) {
            if ($msg->role === 'system' && $system === null) {
                $system = $msg->content;
            } else {
                $chatMessages[] = $msg;
            }
        }
        return [$system, $chatMessages];
    }

    /**
     * Builds the JSON payload for the Anthropic Messages API.
     *
     * @param LlmMessage[] $messages Chat messages (system already removed).
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, string $model, ?string $system, array $options, bool $stream): array
    {
        $payload = [
            'model' => $model,
            'max_tokens' => (int)($options['max_tokens'] ?? 4096),
            'messages' => array_map(fn(LlmMessage $m) => $m->toArray(), $messages),
            'stream' => $stream,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
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
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        return $curl;
    }
}
