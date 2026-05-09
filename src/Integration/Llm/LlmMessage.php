<?php
declare(strict_types=1);

namespace App\Integration\Llm;

/**
 * Represents a single message in an LLM conversation turn.
 *
 * Role values mirror the OpenAI / Anthropic convention (user, assistant,
 * system) so that message arrays can be forwarded to any provider without
 * transformation. This DTO is used both when building the history array
 * sent to the LLM and when recording results back to the database.
 */
class LlmMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
    ) {
    }

    /**
     * Serializes the message to the provider-agnostic array format
     * expected by all three supported clients.
     *
     * @return array{role: string, content: string}
     */
    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
