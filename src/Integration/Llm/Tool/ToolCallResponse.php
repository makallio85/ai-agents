<?php
declare(strict_types=1);

namespace App\Integration\Llm\Tool;

/**
 * Returned by OpenAiClient::completeWithTools() after each LLM turn.
 *
 * A turn either produces text (the LLM is done) or one/more tool calls
 * (the LLM wants to invoke external functions before answering). Exactly
 * one of $content or $toolCalls will be non-empty at a time.
 *
 * AgentLoopService checks isDone() to decide whether to continue looping.
 */
class ToolCallResponse
{
    /**
     * @param string $content Final text from the LLM (non-empty when isDone() is true).
     * @param ToolCall[] $toolCalls Tool invocations requested by the LLM (non-empty when !isDone()).
     * @param string $model Model identifier returned by the API.
     * @param int|null $tokensUsed Combined input + output token count when available.
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls,
        public readonly string $model,
        public readonly ?int $tokensUsed,
    ) {
    }

    /**
     * Returns true when the LLM produced a final text answer and no further
     * tool calls need to be executed.
     */
    public function isDone(): bool
    {
        return empty($this->toolCalls);
    }
}
