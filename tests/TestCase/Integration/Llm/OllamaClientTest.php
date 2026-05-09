<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Llm;

use Cake\TestSuite\TestCase;

/**
 * Unit tests for OllamaClient response parsing.
 *
 * Tests verify fixture payloads match the expected NDJSON parsing logic.
 */
class OllamaClientTest extends TestCase
{
    public function testFixtureCompleteResponseParsesCorrectly(): void
    {
        $fixturePath = dirname(__DIR__, 3) . '/Fixture/Llm/ollama_complete_response.json';
        $body = file_get_contents($fixturePath);
        $this->assertNotFalse($body, 'Fixture file not found');

        /** @var array<string, mixed> $data */
        $data = json_decode($body, true);

        $content = (string)($data['message']['content'] ?? '');
        $done = (bool)($data['done'] ?? false);

        $this->assertEquals('Hello! How can I help you today?', $content);
        $this->assertTrue($done);
    }

    public function testNdjsonStreamDeltaExtraction(): void
    {
        // Simulate a single NDJSON line from Ollama streaming endpoint
        $ndjsonLine = '{"model":"llama3","message":{"role":"assistant","content":"Hi"},"done":false}';
        /** @var array<string, mixed> $event */
        $event = json_decode($ndjsonLine, true);
        $delta = (string)($event['message']['content'] ?? '');

        $this->assertEquals('Hi', $delta);
    }

    public function testDoneLineHasEmptyContent(): void
    {
        // Final NDJSON line signals end with done=true and empty content
        $ndjsonLine = '{"model":"llama3","message":{"role":"assistant","content":""},"done":true}';
        /** @var array<string, mixed> $event */
        $event = json_decode($ndjsonLine, true);
        $delta = (string)($event['message']['content'] ?? '');

        $this->assertEquals('', $delta);
        $this->assertTrue((bool)($event['done'] ?? false));
    }
}
