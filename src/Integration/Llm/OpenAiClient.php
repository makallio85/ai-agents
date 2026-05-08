<?php
declare(strict_types=1);

namespace App\Integration\Llm;

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
