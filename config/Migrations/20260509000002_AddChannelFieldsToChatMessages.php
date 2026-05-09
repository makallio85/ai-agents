<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds channel + delivery + handoff metadata to chat_messages.
 *
 * The role column is preserved (user|assistant|system) so message history
 * still feeds LLM providers directly. New columns capture: which channel
 * carried the message, inbound vs outbound direction, the provider's
 * external id (WhatsApp wamid, email Message-ID), delivery status, content
 * type for media, and sender_user_id when a human took over from the agent.
 *
 * Defaults are chosen so existing rows remain valid: channel='web',
 * direction='inbound' (will be backfilled for existing rows in the same
 * migration), status='received', content_type='text'.
 */
class AddChannelFieldsToChatMessages extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('chat_messages');
        $table->addColumn('channel', 'string', [
                  'limit' => 30,
                  'null' => false,
                  'default' => 'web',
                  'after' => 'role',
              ])
              ->addColumn('direction', 'string', [
                  'limit' => 10,
                  'null' => false,
                  'default' => 'inbound',
                  'comment' => 'inbound|outbound',
                  'after' => 'channel',
              ])
              ->addColumn('sender_user_id', 'integer', [
                  'null' => true,
                  'default' => null,
                  'comment' => 'Set when a human sent an outbound message; NULL means LLM',
                  'after' => 'direction',
              ])
              ->addColumn('content_type', 'string', [
                  'limit' => 30,
                  'null' => false,
                  'default' => 'text',
                  'comment' => 'text|image|audio|document|template',
                  'after' => 'content',
              ])
              ->addColumn('media_url', 'string', [
                  'limit' => 500,
                  'null' => true,
                  'default' => null,
                  'after' => 'content_type',
              ])
              ->addColumn('media_mime_type', 'string', [
                  'limit' => 100,
                  'null' => true,
                  'default' => null,
                  'after' => 'media_url',
              ])
              ->addColumn('external_message_id', 'string', [
                  'limit' => 255,
                  'null' => true,
                  'default' => null,
                  'comment' => 'Provider id (wamid, email Message-ID)',
                  'after' => 'media_mime_type',
              ])
              ->addColumn('external_thread_id', 'string', [
                  'limit' => 255,
                  'null' => true,
                  'default' => null,
                  'after' => 'external_message_id',
              ])
              ->addColumn('status', 'string', [
                  'limit' => 20,
                  'null' => false,
                  'default' => 'received',
                  'comment' => 'queued|sent|delivered|read|failed|received',
                  'after' => 'external_thread_id',
              ])
              ->addColumn('error_code', 'string', [
                  'limit' => 50,
                  'null' => true,
                  'default' => null,
                  'after' => 'status',
              ])
              ->addColumn('error_message', 'text', [
                  'null' => true,
                  'default' => null,
                  'after' => 'error_code',
              ])
              ->addColumn('metadata', 'text', [
                  'null' => true,
                  'default' => null,
                  'comment' => 'JSON catch-all for raw provider payload',
                  'after' => 'error_message',
              ])
              ->addColumn('sent_at', 'datetime', [
                  'null' => true,
                  'default' => null,
                  'after' => 'metadata',
              ])
              ->addColumn('delivered_at', 'datetime', [
                  'null' => true,
                  'default' => null,
                  'after' => 'sent_at',
              ])
              ->addColumn('read_at', 'datetime', [
                  'null' => true,
                  'default' => null,
                  'after' => 'delivered_at',
              ])
              ->addIndex(['channel', 'external_message_id', 'direction'], [
                  'unique' => true,
                  'name' => 'uq_chat_messages_channel_extid_direction',
              ])
              ->addIndex(['direction', 'created'], ['name' => 'idx_chat_messages_direction_created'])
              ->addForeignKey('sender_user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
              ->update();

        // Backfill direction so existing rows reflect their role:
        //   role='user'                 -> inbound  (already the default, but explicit)
        //   role='assistant' or 'system' -> outbound
        $this->execute("UPDATE chat_messages SET direction = 'outbound' WHERE role IN ('assistant', 'system')");
        // Existing assistant messages were sent by the agent (LLM); status reflects delivery success since the SSE
        // flow only persists assistant messages after a successful stream. Mark them as 'sent' for accuracy.
        $this->execute("UPDATE chat_messages SET status = 'sent' WHERE role = 'assistant'");
    }

    public function down(): void
    {
        $table = $this->table('chat_messages');
        $table->dropForeignKey('sender_user_id')
              ->removeIndexByName('uq_chat_messages_channel_extid_direction')
              ->removeIndexByName('idx_chat_messages_direction_created')
              ->removeColumn('channel')
              ->removeColumn('direction')
              ->removeColumn('sender_user_id')
              ->removeColumn('content_type')
              ->removeColumn('media_url')
              ->removeColumn('media_mime_type')
              ->removeColumn('external_message_id')
              ->removeColumn('external_thread_id')
              ->removeColumn('status')
              ->removeColumn('error_code')
              ->removeColumn('error_message')
              ->removeColumn('metadata')
              ->removeColumn('sent_at')
              ->removeColumn('delivered_at')
              ->removeColumn('read_at')
              ->update();
    }
}
