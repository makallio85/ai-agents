<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for chat_messages.
 *
 * Provides a minimal two-turn conversation (user + assistant) belonging to
 * session 1, so history assembly and message count tests can run without
 * hitting a live LLM.
 */
class ChatMessagesFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1,
            'chat_session_id' => 1,
            'role' => 'user',
            'content' => 'Hello, what can you do?',
            'tokens_used' => null,
            'model_used' => null,
            'created' => '2026-01-01 10:00:01',
            'modified' => '2026-01-01 10:00:01',
        ],
        [
            'id' => 2,
            'chat_session_id' => 1,
            'role' => 'assistant',
            'content' => 'I can help you manage DevOps tasks.',
            'tokens_used' => 42,
            'model_used' => 'gpt-4o',
            'created' => '2026-01-01 10:00:02',
            'modified' => '2026-01-01 10:00:02',
        ],
    ];
}
