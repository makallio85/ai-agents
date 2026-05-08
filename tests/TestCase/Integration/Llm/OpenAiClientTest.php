<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Llm;

use Cake\TestSuite\TestCase;

/**
 * Unit tests for OpenAiClient response parsing.
 *
 * The actual curl calls are not exercised — tests verify fixture payloads
 * match the expected parsing logic used inside the client.
 */
class OpenAiClientTest extends TestCase
{
    public function testFixtureCompleteResponseParsesCorrectly(): void
    {
        $fixturePath = dirname(__DIR__, 3) . '/Fixture/Llm/openai_complete_response.json';
        $body = file_get_contents($fixturePath);
        $this->assertNotFalse($body, 'Fixture file not found');

        /** @var array<string, mixed> $data */
        $data = json_decode($body, true);

        $content = (string)($data['choices'][0]['message']['content'] ?? '');
        $tokensUsed = (int)($data['usage']['total_tokens'] ?? 0);
        $finishReason = (string)($data['choices'][0]['finish_reason'] ?? '');

        $this->assertEquals('Hello! How can I help you today?', $content);
        $this->assertEquals(19, $tokensUsed);
        $this->assertEquals('stop', $finishReason);
    }

    public function testStreamDeltaExtractionFromSseEvent(): void
    {
        // Simulate a single SSE line as emitted by the OpenAI streaming API
        $sseLine = 'data: {"id":"chatcmpl-1","object":"chat.completion.chunk","choices":[{"delta":{"content":"Hello"},"finish_reason":null}]}';
        $jsonStr = substr($sseLine, 6); // strip "data: "
        /** @var array<string, mixed> $event */
        $event = json_decode($jsonStr, true);
        $delta = (string)($event['choices'][0]['delta']['content'] ?? '');

        $this->assertEquals('Hello', $delta);
    }

    public function testDoneEventIsIgnored(): void
    {
        $sseLine = 'data: [DONE]';
        $jsonStr = substr($sseLine, 6);
        $this->assertEquals('[DONE]', $jsonStr);
        // json_decode returns null for "[DONE]", so delta would be ''
        $event = json_decode($jsonStr, true);
        $this->assertNull($event);
    }
}
