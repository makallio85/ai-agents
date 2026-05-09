<?php
declare(strict_types=1);

namespace App\Integration\Llm;

use Cake\Core\Configure;
use RuntimeException;

/**
 * Instantiates the correct LLM client based on the agent's llm_provider field.
 *
 * This factory is the single point where provider-specific configuration
 * (API keys, base URLs) is read from the environment. Business logic only
 * ever calls LlmClientInterface methods; the factory ensures the right
 * concrete implementation is injected.
 *
 * Supported provider strings (stored in agents.llm_provider):
 *   "openai"    — OpenAI Chat Completions API
 *   "anthropic" — Anthropic Messages API
 *   "ollama"    — Local Ollama server
 *
 * Required environment variables (set in config/.env):
 *   OPENAI_API_KEY     — required when provider is "openai"
 *   ANTHROPIC_API_KEY  — required when provider is "anthropic"
 *   OLLAMA_BASE_URL    — optional, defaults to http://localhost:11434
 */
class LlmClientFactory
{
    /**
     * Creates and returns the LLM client for the given provider string.
     *
     * @throws RuntimeException When the provider is unsupported or required config is missing.
     */
    public function make(string $provider): LlmClientInterface
    {
        return match (strtolower($provider)) {
            'openai' => $this->makeOpenAi(),
            'anthropic' => $this->makeAnthropic(),
            'ollama' => $this->makeOllama(),
            default => throw new RuntimeException("Unsupported LLM provider: '{$provider}'"),
        };
    }

    private function makeOpenAi(): OpenAiClient
    {
        $key = (string)(Configure::read('Llm.openaiApiKey') ?? env('OPENAI_API_KEY', ''));
        if (empty($key)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured');
        }
        return new OpenAiClient($key);
    }

    private function makeAnthropic(): AnthropicClient
    {
        $key = (string)(Configure::read('Llm.anthropicApiKey') ?? env('ANTHROPIC_API_KEY', ''));
        if (empty($key)) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured');
        }
        return new AnthropicClient($key);
    }

    private function makeOllama(): OllamaClient
    {
        $baseUrl = (string)(Configure::read('Llm.ollamaBaseUrl') ?? env('OLLAMA_BASE_URL', 'http://localhost:11434'));
        return new OllamaClient($baseUrl);
    }
}
