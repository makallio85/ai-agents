<?php
declare(strict_types=1);

namespace App\Integration\Llm\Tool;

/**
 * Defines a callable tool that can be offered to the LLM during a conversation.
 *
 * Each ToolDefinition maps directly to an OpenAI function-calling schema. The
 * name must match what the AgentLoopService uses to look up and dispatch the
 * actual PHP callable. The parameters schema follows JSON Schema draft-07.
 *
 * Used by GitHubToolProvider to register GitHub operations and by OpenAiClient
 * to populate the `tools` array in the Chat Completions request.
 */
class ToolDefinition
{
    /**
     * @param string $name Unique snake_case name matching the dispatch key in ToolRegistry.
     * @param string $description Plain-English description that helps the LLM decide when to call this tool.
     * @param array<string, mixed> $parameters JSON Schema object describing the accepted arguments.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
    ) {
    }

    /**
     * Serializes to the OpenAI tools array entry format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
