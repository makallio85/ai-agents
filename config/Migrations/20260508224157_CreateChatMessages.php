<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates the chat_messages table.
 *
 * Stores individual messages within a chat session. Each row represents one
 * turn: either a user message, an assistant (LLM) reply, or a system prompt
 * injection. The role column mirrors the OpenAI / Anthropic convention so that
 * message history can be fed directly to any LLM provider without mapping.
 *
 * tokens_used and model_used are recorded on assistant messages for cost
 * tracking and audit purposes; they are null on user/system messages.
 */
class CreateChatMessages extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('chat_messages');
        $table->addColumn('chat_session_id', 'integer', ['null' => false])
              ->addColumn('role', 'string', ['limit' => 20, 'null' => false, 'comment' => 'user|assistant|system'])
              ->addColumn('content', 'text', ['null' => false, 'limit' => \Migrations\Db\Adapter\MysqlAdapter::TEXT_LONG])
              ->addColumn('tokens_used', 'integer', ['null' => true, 'default' => null])
              ->addColumn('model_used', 'string', ['limit' => 100, 'null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['chat_session_id'], ['name' => 'idx_chat_messages_session_id'])
              ->addForeignKey('chat_session_id', 'chat_sessions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
