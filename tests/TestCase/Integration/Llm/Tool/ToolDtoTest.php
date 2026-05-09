<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Llm\Tool;

use App\Integration\Llm\Tool\ToolCall;
use App\Integration\Llm\Tool\ToolCallResponse;
use App\Integration\Llm\Tool\ToolDefinition;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for the Tool DTO layer used by AgentLoopService.
 *
 * Covers ToolDefinition::toArray(), ToolCall construction, and
 * ToolCallResponse::isDone() which drives the ReAct loop termination logic.
 */
class ToolDtoTest extends TestCase
{
    // ── ToolDefinition ────────────────────────────────────────────────────────

    public function testToolDefinitionToArrayReturnsOpenAiFormat(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'owner' => ['type' => 'string'],
            ],
            'required' => ['owner'],
        ];

        $def = new ToolDefinition(
            name: 'github_list_repos',
            description: 'List repos',
            parameters: $schema,
        );

        $expected = [
            'type' => 'function',
            'function' => [
                'name' => 'github_list_repos',
                'description' => 'List repos',
                'parameters' => $schema,
            ],
        ];

        $this->assertEquals($expected, $def->toArray());
    }

    public function testToolDefinitionStoresNameAndDescription(): void
    {
        $def = new ToolDefinition(
            name: 'my_tool',
            description: 'Does something',
            parameters: ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
        );

        $this->assertEquals('my_tool', $def->name);
        $this->assertEquals('Does something', $def->description);
    }

    // ── ToolCall ──────────────────────────────────────────────────────────────

    public function testToolCallStoresIdNameArguments(): void
    {
        $call = new ToolCall(id: 'call_abc', name: 'github_get_file', arguments: ['owner' => 'acme', 'repo' => 'app', 'path' => 'README.md']);

        $this->assertEquals('call_abc', $call->id);
        $this->assertEquals('github_get_file', $call->name);
        $this->assertEquals(['owner' => 'acme', 'repo' => 'app', 'path' => 'README.md'], $call->arguments);
    }

    // ── ToolCallResponse ──────────────────────────────────────────────────────

    public function testIsDoneReturnsTrueWhenNoToolCalls(): void
    {
        $response = new ToolCallResponse(
            content: 'Final answer here.',
            toolCalls: [],
            model: 'gpt-4o',
            tokensUsed: 42,
        );

        $this->assertTrue($response->isDone());
    }

    public function testIsDoneReturnsFalseWhenToolCallsPresent(): void
    {
        $toolCall = new ToolCall(id: 'call_1', name: 'github_list_repos', arguments: []);
        $response = new ToolCallResponse(
            content: '',
            toolCalls: [$toolCall],
            model: 'gpt-4o',
            tokensUsed: 10,
        );

        $this->assertFalse($response->isDone());
    }

    public function testToolCallResponseStoresFields(): void
    {
        $response = new ToolCallResponse(
            content: 'Done!',
            toolCalls: [],
            model: 'gpt-4o-mini',
            tokensUsed: 100,
        );

        $this->assertEquals('Done!', $response->content);
        $this->assertEquals('gpt-4o-mini', $response->model);
        $this->assertEquals(100, $response->tokensUsed);
    }
}
