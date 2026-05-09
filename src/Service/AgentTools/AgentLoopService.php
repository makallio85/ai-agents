<?php
declare(strict_types=1);

namespace App\Service\AgentTools;

use App\Integration\Llm\LlmMessage;
use App\Integration\Llm\LlmResponse;
use App\Integration\Llm\OpenAiClient;
use App\Integration\Llm\Tool\ToolCall;
use App\Integration\Llm\Tool\ToolCallResponse;
use App\Integration\Llm\Tool\ToolDefinition;
use Cake\Log\LogTrait;
use RuntimeException;

/**
 * Executes the agentic ReAct loop for tool-enabled agents.
 *
 * Instead of a single LLM call, the loop iterates:
 *   1. Send messages + tool definitions to the LLM.
 *   2. If the LLM returns tool_calls → execute each tool via GitHubToolProvider,
 *      append the assistant turn and tool results to the message history, repeat.
 *   3. If the LLM returns content → stream it to the browser and stop.
 *
 * The $onChunk callback is invoked for tool progress events (so the user can
 * see what the agent is doing) and then again for each text delta in the final
 * answer. Events are JSON-encoded SSE data payloads.
 *
 * A hard cap of MAX_ITERATIONS prevents infinite loops if the LLM keeps
 * requesting tools without ever producing a final answer.
 *
 * Only OpenAiClient supports tool calling currently; injecting any other
 * LlmClientInterface will throw an exception.
 */
class AgentLoopService
{
    use LogTrait;

    private const MAX_ITERATIONS = 10;

    public function __construct(
        private readonly OpenAiClient $client,
        private readonly GitHubToolProvider $toolProvider,
    ) {
    }

    /**
     * Runs the agentic loop until the LLM produces a final text response.
     *
     * @param LlmMessage[] $messages Full conversation history including system prompt.
     * @param array<string, mixed> $options Provider options (model, temperature, etc.).
     * @param callable(string): void $onEvent Called for every SSE event payload (tool progress + final chunks).
     * @throws RuntimeException When MAX_ITERATIONS is exceeded without a final answer.
     */
    public function run(array $messages, array $options, callable $onEvent): LlmResponse
    {
        $tools = $this->toolProvider->getDefinitions();

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $response = $this->client->completeWithTools($messages, $tools, $options);

            if ($response->isDone()) {
                // The LLM produced a final text answer — emit it as a single chunk.
                $this->emit($onEvent, ['type' => 'chunk', 'content' => $response->content]);

                return new LlmResponse(
                    content: $response->content,
                    tokensUsed: $response->tokensUsed,
                    model: $response->model,
                    finishReason: 'stop',
                );
            }

            // Append the assistant turn (with its tool_calls) to history
            $messages[] = $this->buildAssistantToolCallMessage($response);

            // Execute each requested tool and feed results back
            foreach ($response->toolCalls as $toolCall) {
                $result = $this->executeToolCall($toolCall, $onEvent);
                $messages[] = new LlmMessage(
                    role: 'tool',
                    content: $result,
                    toolCallId: $toolCall->id,
                );
            }
        }

        throw new RuntimeException(
            'Agent loop exceeded maximum iterations (' . self::MAX_ITERATIONS . ') without producing a final answer.'
        );
    }

    /**
     * Builds the assistant message that carries the LLM's tool_calls back
     * into the conversation history so the model knows what it requested.
     */
    private function buildAssistantToolCallMessage(ToolCallResponse $response): LlmMessage
    {
        $toolCalls = array_map(fn(ToolCall $tc) => [
            'id' => $tc->id,
            'type' => 'function',
            'function' => [
                'name' => $tc->name,
                'arguments' => json_encode($tc->arguments),
            ],
        ], $response->toolCalls);

        return new LlmMessage(
            role: 'assistant',
            content: '',
            toolCalls: $toolCalls,
        );
    }

    /**
     * Dispatches a single tool call to GitHubToolProvider, emits a progress
     * event, and returns the plain-text result string.
     *
     * @param callable(string): void $onEvent
     */
    private function executeToolCall(ToolCall $toolCall, callable $onEvent): string
    {
        // Notify the frontend that a tool is being executed
        $this->emit($onEvent, [
            'type' => 'tool_call',
            'tool' => $toolCall->name,
            'args' => $toolCall->arguments,
        ]);

        $this->log(
            "AgentLoop executing tool: {$toolCall->name}",
            'info',
            ['scope' => 'agent_loop', 'args' => $toolCall->arguments],
        );

        try {
            $result = $this->toolProvider->dispatch($toolCall->name, $toolCall->arguments);
        } catch (\Throwable $e) {
            $result = "Tool execution failed: {$e->getMessage()}";
            $this->log(
                "AgentLoop tool error: {$toolCall->name} — {$e->getMessage()}",
                'error',
                ['scope' => 'agent_loop'],
            );
        }

        // Notify the frontend of the tool result
        $this->emit($onEvent, [
            'type' => 'tool_result',
            'tool' => $toolCall->name,
            'result' => mb_substr($result, 0, 500), // truncate for SSE; full result goes to LLM
        ]);

        return $result;
    }

    /**
     * JSON-encodes $data and passes the resulting string to $onEvent.
     * Silently skips emission if encoding fails (prevents type errors from
     * propagating into the loop).
     *
     * @param callable(string): void $onEvent
     * @param array<string, mixed> $data
     */
    private function emit(callable $onEvent, array $data): void
    {
        $encoded = json_encode($data);
        if ($encoded !== false) {
            $onEvent($encoded);
        }
    }
}
