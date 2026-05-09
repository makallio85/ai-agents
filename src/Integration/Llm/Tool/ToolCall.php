<?php
declare(strict_types=1);

namespace App\Integration\Llm\Tool;

/**
 * Represents a single tool invocation requested by the LLM.
 *
 * When OpenAI returns finish_reason = 'tool_calls', the response contains one
 * or more ToolCall objects. AgentLoopService iterates over them, dispatches
 * each to the corresponding PHP callable via GitHubToolProvider, and feeds
 * the results back to the LLM as tool-role messages.
 */
class ToolCall
{
    /**
     * @param string $id Unique ID assigned by the LLM (must be echoed back in the tool result).
     * @param string $name Function name matching a registered ToolDefinition.
     * @param array<string, mixed> $arguments Decoded JSON arguments from the LLM.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
