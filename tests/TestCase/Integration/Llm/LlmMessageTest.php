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
}
