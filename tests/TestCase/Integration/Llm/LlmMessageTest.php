<?php
declare(strict_types=1);

namespace App\Test\TestCase\Integration\Llm;

use App\Integration\Llm\LlmMessage;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for the LlmMessage DTO.
 *
 * Verifies that the DTO correctly stores role and content, and that
 * toArray() produces the format all LLM clients expect.
 */
class LlmMessageTest extends TestCase
{
    public function testConstructorStoresRoleAndContent(): void
    {
        $msg = new LlmMessage('user', 'Hello!');
        $this->assertEquals('user', $msg->role);
        $this->assertEquals('Hello!', $msg->content);
    }

    public function testToArrayReturnsExpectedShape(): void
    {
        $msg = new LlmMessage('assistant', 'Hi there');
        $arr = $msg->toArray();
        $this->assertEquals(['role' => 'assistant', 'content' => 'Hi there'], $arr);
    }

    public function testSystemRoleIsPreserved(): void
    {
        $msg = new LlmMessage('system', 'You are a helpful assistant.');
        $this->assertEquals('system', $msg->role);
    }

    public function testAssistantWithToolCallsSerializesToolCallsAndNullContent(): void
    {
        $toolCalls = [
            ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'github_list_repos', 'arguments' => '{}']],
        ];
        $msg = new LlmMessage(role: 'assistant', content: '', toolCalls: $toolCalls);
        $arr = $msg->toArray();

        $this->assertEquals('assistant', $arr['role']);
        $this->assertNull($arr['content']); // empty content → null for API compatibility
        $this->assertEquals($toolCalls, $arr['tool_calls']);
    }

    public function testToolRoleSerializesToolCallIdAndContent(): void
    {
        $msg = new LlmMessage(role: 'tool', content: 'acme/app, acme/infra', toolCallId: 'call_1');
        $arr = $msg->toArray();

        $this->assertEquals('tool', $arr['role']);
        $this->assertEquals('call_1', $arr['tool_call_id']);
        $this->assertEquals('acme/app, acme/infra', $arr['content']);
    }

    public function testAssistantWithNonEmptyContentPreservesItWhenToolCallsPresent(): void
    {
        $toolCalls = [['id' => 'call_2', 'type' => 'function', 'function' => ['name' => 'github_get_file', 'arguments' => '{}']]];
        $msg = new LlmMessage(role: 'assistant', content: 'Thinking...', toolCalls: $toolCalls);
        $arr = $msg->toArray();

        $this->assertEquals('Thinking...', $arr['content']);
    }
}
