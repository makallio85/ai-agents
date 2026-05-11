<?php
declare(strict_types=1);

namespace App\Integration\Llm;

/**
 * Represents a single message in an LLM conversation turn.
 *
 * Role values mirror the OpenAI / Anthropic convention:
 *   - system    — prepended instructions / persona
 *   - user      — end-user input
 *   - assistant — LLM response (may include tool_calls when agentic)
 *   - tool      — result of a tool call returned to the LLM
 *
 * The optional $toolCalls and $toolCallId fields support the OpenAI function-
 * calling protocol used by AgentLoopService. They are null in normal (non-
 * agentic) conversations and do not affect the existing complete/stream paths.
 */
class LlmMessage
{
    /**
     * @param string $role Message role (system | user | assistant | tool).
     * @param string $content Text content (empty string for assistant messages that only contain tool_calls).
     * @param array<int, array<string, mixed>>|null $toolCalls OpenAI tool_calls array attached to an assistant turn.
     * @param string|null $toolCallId Required when role = 'tool'; links the result to the original ToolCall id.
     */
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
    ) {
    }

    /**
     * Serializes the message to the wire format expected by the provider.
     *
     * - Standard messages: {role, content}
     * - Assistant with tool calls: {role, content: null, tool_calls: [...]}
     * - Tool result: {role: 'tool', tool_call_id, content}
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->role === 'assistant' && $this->toolCalls !== null) {
            return [
                'role' => 'assistant',
                'content' => $this->content !== '' ? $this->content : null,
                'tool_calls' => $this->toolCalls,
            ];
        }

        if ($this->role === 'tool') {
            return [
                'role' => 'tool',
                'tool_call_id' => $this->toolCallId,
                'content' => $this->content,
            ];
        }

        return ['role' => $this->role, 'content' => $this->content];
    }
}
