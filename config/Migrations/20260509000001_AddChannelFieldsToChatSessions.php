<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds channel routing and human-handoff fields to chat_sessions.
 *
 * Existing rows belong to the web channel and are agent-handled; defaults
 * preserve current behaviour. last_inbound_at drives WhatsApp's 24-hour
 * messaging-window check. assignment_state + assigned_user_id support
 * routing inbound to a human instead of the LLM handler.
 */
class AddChannelFieldsToChatSessions extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('chat_sessions');
        $table->addColumn('channel', 'string', [
                  'limit' => 30,
                  'null' => false,
                  'default' => 'web',
                  'comment' => 'web|whatsapp|email|...',
                  'after' => 'agent_id',
              ])
              ->addColumn('channel_external_id', 'string', [
                  'limit' => 255,
                  'null' => true,
                  'default' => null,
                  'comment' => 'Provider identifier (WhatsApp wa_id, email thread reference, etc.)',
                  'after' => 'channel',
              ])
              ->addColumn('last_inbound_at', 'datetime', [
                  'null' => true,
                  'default' => null,
                  'comment' => 'Drives 24h messaging-window for channels that have one',
                  'after' => 'channel_external_id',
              ])
              ->addColumn('assignment_state', 'string', [
                  'limit' => 20,
                  'null' => false,
                  'default' => 'agent',
                  'comment' => 'agent|pending_human|human',
                  'after' => 'last_inbound_at',
              ])
              ->addColumn('assigned_user_id', 'integer', [
                  'null' => true,
                  'default' => null,
                  'comment' => 'Human currently handling this session, when escalated',
                  'after' => 'assignment_state',
              ])
              ->addColumn('assigned_at', 'datetime', [
                  'null' => true,
                  'default' => null,
                  'after' => 'assigned_user_id',
              ])
              ->addColumn('escalation_reason', 'string', [
                  'limit' => 255,
                  'null' => true,
                  'default' => null,
                  'after' => 'assigned_at',
              ])
              ->addIndex(['user_id', 'agent_id', 'channel'], ['name' => 'idx_chat_sessions_user_agent_channel'])
              ->addIndex(['channel', 'channel_external_id'], ['name' => 'idx_chat_sessions_channel_ext'])
              ->addIndex(['assignment_state', 'assigned_user_id'], ['name' => 'idx_chat_sessions_assignment'])
              ->addForeignKey('assigned_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
              ->update();
    }
}
