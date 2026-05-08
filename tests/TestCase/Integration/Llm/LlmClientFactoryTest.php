<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Llm;

use App\Integration\Llm\AnthropicClient;
use App\Integration\Llm\LlmClientFactory;
use App\Integration\Llm\OllamaClient;
use App\Integration\Llm\OpenAiClient;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use RuntimeException;

/**
 * Unit tests for LlmClientFactory.
 *
 * Verifies that the factory resolves the correct concrete client for each
 * supported provider string and raises descriptive errors for unknown
 * providers or missing credentials.
 */
class LlmClientFactoryTest extends TestCase
{
    private LlmClientFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new LlmClientFactory();
    }

    protected function tearDown(): void
    {
        // Clean up any config values set during tests
        Configure::delete('Llm');
        parent::tearDown();
    }

    public function testMakeReturnsOpenAiClientForOpenaiProvider(): void
    {
        Configure::write('Llm.openaiApiKey', 'test-key');
        $client = $this->factory->make('openai');
        $this->assertInstanceOf(OpenAiClient::class, $client);
    }

    public function testMakeReturnsAnthropicClientForAnthropicProvider(): void
    {
        Configure::write('Llm.anthropicApiKey', 'test-key');
        $client = $this->factory->make('anthropic');
        $this->assertInstanceOf(AnthropicClient::class, $client);
    }

    public function testMakeReturnsOllamaClientForOllamaProvider(): void
    {
        $client = $this->factory->make('ollama');
        $this->assertInstanceOf(OllamaClient::class, $client);
    }

    public function testMakeIsCaseInsensitive(): void
    {
        Configure::write('Llm.openaiApiKey', 'test-key');
        $client = $this->factory->make('OpenAI');
        $this->assertInstanceOf(OpenAiClient::class, $client);
    }

    public function testMakeThrowsForUnsupportedProvider(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unsupported LLM provider: 'gemini'");
        $this->factory->make('gemini');
    }

    public function testMakeThrowsForOpenAiWhenKeyMissing(): void
    {
        Configure::delete('Llm.openaiApiKey');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY is not configured');
        $this->factory->make('openai');
    }

    public function testMakeThrowsForAnthropicWhenKeyMissing(): void
    {
        Configure::delete('Llm.anthropicApiKey');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ANTHROPIC_API_KEY is not configured');
        $this->factory->make('anthropic');
    }
}
