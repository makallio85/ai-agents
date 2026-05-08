<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates the chat_sessions table.
 *
 * Stores persistent conversation sessions between a user and a specific agent.
 * Each session accumulates chat_messages over time and can be resumed across
 * page loads. Separate from the existing conversations table, which is
 * purpose-built for the DevOps issue-parsing workflow.
 */
class CreateChatSessions extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('chat_sessions');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('title', 'string', ['limit' => 255, 'null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'], ['name' => 'idx_chat_sessions_user_id'])
              ->addIndex(['agent_id'], ['name' => 'idx_chat_sessions_agent_id'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
