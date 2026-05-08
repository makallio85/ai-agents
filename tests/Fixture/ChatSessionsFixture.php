<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for chat_sessions.
 *
 * Provides two sessions: one for user 1 with an LLM-configured agent,
 * and one for user 2, so ownership-isolation tests can be written.
 */
class ChatSessionsFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1,
            'user_id' => 1,
            'agent_id' => 1,
            'title' => 'First session',
            'created' => '2026-01-01 10:00:00',
            'modified' => '2026-01-01 10:00:00',
        ],
        [
            'id' => 2,
            'user_id' => 1,
            'agent_id' => 1,
            'title' => null,
            'created' => '2026-01-01 11:00:00',
            'modified' => '2026-01-01 11:00:00',
        ],
    ];
}
