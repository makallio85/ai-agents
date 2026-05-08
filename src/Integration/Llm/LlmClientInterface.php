<?php
declare(strict_types=1);

namespace App\Integration\Llm;

/**
 * Contract for all LLM provider clients.
 *
 * Every provider (OpenAI, Anthropic, Ollama) must implement this interface
 * so that LlmService can swap backends purely based on the agent's
 * llm_provider field without any conditional branching in business logic.
 *
 * Both methods accept a flat array of LlmMessage objects representing the
 * full conversation history to send. The system prompt, if any, must be
 * included as the first message with role "system" before calling.
 */
interface LlmClientInterface
{
    /**
     * Sends the full message history to the LLM and waits for the complete
     * response before returning. Suitable for short, non-interactive calls
     * where streaming is not required.
     *
     * @param LlmMessage[] $messages Full conversation history including system prompt.
     * @param array<string, mixed> $options Provider-specific overrides (e.g. temperature, max_tokens).
     */
    public function complete(array $messages, array $options = []): LlmResponse;

    /**
     * Sends the full message history to the LLM and streams the response
     * token by token. The $onChunk callback is invoked once per text delta
     * as it arrives from the provider. Returns the assembled LlmResponse
     * once the stream is complete.
     *
     * The $onChunk callback receives a single string argument containing the
     * incremental text delta. It must not throw; any exception will abort
     * the stream and the partial response will be lost.
     *
     * @param LlmMessage[] $messages Full conversation history including system prompt.
     * @param array<string, mixed> $options Provider-specific overrides.
     * @param callable(string): void $onChunk Called for each streamed text delta.
     */
    public function stream(array $messages, array $options, callable $onChunk): LlmResponse;
}
