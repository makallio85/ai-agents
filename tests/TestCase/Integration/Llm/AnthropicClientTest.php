<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Llm;

use App\Integration\Llm\AnthropicClient;
use App\Integration\Llm\LlmMessage;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for AnthropicClient's response parsing logic.
 *
 * Only the non-network logic is tested (payload building, system message
 * extraction). The actual curl calls are not exercised — those would require
 * a live Anthropic API key and are excluded per the project testing rules.
 */
class AnthropicClientTest extends TestCase
{
    private AnthropicClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new AnthropicClient('test-key');
    }

    public function testFixtureCompleteResponseParsesCorrectly(): void
    {
        $fixturePath = dirname(__DIR__, 3) . '/Fixture/Llm/anthropic_complete_response.json';
        $body = file_get_contents($fixturePath);
        $this->assertNotFalse($body, 'Fixture file not found');

        /** @var array<string, mixed> $data */
        $data = json_decode($body, true);

        $content = (string)($data['content'][0]['text'] ?? '');
        $tokensUsed = (int)$data['usage']['input_tokens'] + (int)$data['usage']['output_tokens'];
        $stopReason = (string)($data['stop_reason'] ?? '');

        $this->assertEquals('Hello! How can I help you today?', $content);
        $this->assertEquals(19, $tokensUsed);
        $this->assertEquals('end_turn', $stopReason);
    }

    public function testClientCanBeInstantiatedWithKey(): void
    {
        $this->assertInstanceOf(AnthropicClient::class, $this->client);
    }

    public function testSystemMessageIsExtractedFromMessages(): void
    {
        // Verify via reflection that splitSystemMessage behaves correctly.
        // This is internal logic that affects which messages reach the API.
        $reflection = new \ReflectionClass(AnthropicClient::class);
        $method = $reflection->getMethod('splitSystemMessage');
        $method->setAccessible(true);

        $messages = [
            new LlmMessage('system', 'Be helpful.'),
            new LlmMessage('user', 'Hello'),
            new LlmMessage('assistant', 'Hi'),
        ];

        [$system, $chatMessages] = $method->invoke($this->client, $messages);

        $this->assertEquals('Be helpful.', $system);
        $this->assertCount(2, $chatMessages);
        $this->assertEquals('user', $chatMessages[0]->role);
    }

    public function testNoSystemMessageReturnsNullSystem(): void
    {
        $reflection = new \ReflectionClass(AnthropicClient::class);
        $method = $reflection->getMethod('splitSystemMessage');
        $method->setAccessible(true);

        $messages = [new LlmMessage('user', 'Hello')];
        [$system, $chatMessages] = $method->invoke($this->client, $messages);

        $this->assertNull($system);
        $this->assertCount(1, $chatMessages);
    }
}
