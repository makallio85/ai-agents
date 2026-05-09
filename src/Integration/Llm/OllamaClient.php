<?php
declare(strict_types=1);

namespace App\Integration\Llm;

use RuntimeException;

/**
 * LLM client for locally hosted models via Ollama.
 *
 * Implements both blocking (complete) and streaming (stream) modes using
 * PHP's native curl extension. Ollama's /api/chat endpoint returns
 * newline-delimited JSON (NDJSON) when streaming is enabled; each line is
 * a JSON object with a "message.content" field containing the delta.
 *
 * Configuration is read from environment variables:
 *   OLLAMA_BASE_URL  — base URL of the Ollama server (default: http://localhost:11434)
 *
 * No API key is required. The model is passed per-call via LlmService
 * (e.g. "llama3", "mistral", "phi3").
 */
class OllamaClient implements LlmClientInterface
{
    private string $apiUrl;

    public function __construct(string $baseUrl = 'http://localhost:11434')
    {
        $this->apiUrl = rtrim($baseUrl, '/') . '/api/chat';
    }

    /**
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     */
    public function complete(array $messages, array $options = []): LlmResponse
    {
        $model = (string)($options['model'] ?? '');
        if ($model === '') {
            throw new RuntimeException('Ollama model name is required. Set the model on the agent (e.g. "kahnwong/poro-2:8b-it").');
        }
        $payload = $this->buildPayload($messages, $model, false);

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode Ollama request payload as JSON');
        }

        $curl = $this->createCurl();
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $body = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $httpCode !== 200) {
            throw new RuntimeException("Ollama API error (HTTP {$httpCode}): " . ($body ?: 'empty response'));
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string)$body, true);
        $content = (string)($data['message']['content'] ?? '');
        $finishReason = (bool)($data['done'] ?? false) ? 'stop' : 'length';

        return new LlmResponse($content, null, $model, $finishReason);
    }

    /**
     * @param LlmMessage[] $messages
     * @param array<string, mixed> $options
     * @param callable(string): void $onChunk
     */
    public function stream(array $messages, array $options, callable $onChunk): LlmResponse
    {
        $model = (string)($options['model'] ?? '');
        if ($model === '') {
            throw new RuntimeException('Ollama model name is required. Set the model on the agent (e.g. "kahnwong/poro-2:8b-it").');
        }
        $payload = $this->buildPayload($messages, $model, true);

        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode Ollama streaming payload as JSON');
        }

        $assembled = '';
        $buffer = '';

        $curl = $this->createCurl();
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($ch, string $chunk) use (&$assembled, &$buffer, $onChunk): int {
            // Ollama streams NDJSON; lines may span multiple curl chunks
            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            // Keep the last (potentially incomplete) line in the buffer
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                /** @var array<string, mixed>|null $event */
                $event = json_decode($line, true);
                if (!is_array($event)) {
                    continue;
                }
                $delta = (string)($event['message']['content'] ?? '');
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
            throw new RuntimeException("Ollama streaming API error (HTTP {$httpCode})");
        }

        return new LlmResponse($assembled, null, $model, 'stop');
    }

    /**
     * Builds the JSON payload for the Ollama /api/chat endpoint.
     *
     * @param LlmMessage[] $messages
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, string $model, bool $stream): array
    {
        return [
            'model' => $model,
            'messages' => array_map(fn(LlmMessage $m) => $m->toArray(), $messages),
            'stream' => $stream,
        ];
    }

    /** @return \CurlHandle */
    private function createCurl(): \CurlHandle
    {
        $curl = curl_init($this->apiUrl);
        if ($curl === false) {
            throw new RuntimeException('Failed to initialise curl');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 300,
        ]);
        return $curl;
    }
}
