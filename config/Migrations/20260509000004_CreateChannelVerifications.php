<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * OTP / handshake table for channels that need to verify a sender's identity
 * before linking it to a User.
 *
 * v1 use case: WhatsApp inbound from an unknown phone number — we issue a
 * one-time code, ask the user to reply with it, then link the phone to a
 * User row (existing or freshly-created guest). pending_payload buffers the
 * original inbound body (JSON-encoded InboundEnvelope-like payload) so it
 * can be replayed after verification — we don't create a chat_messages row
 * yet because chat_session_id is NOT NULL and we have no user.
 *
 * code_hash is bcrypt; we never store the code in plaintext. Email won't
 * use this table (From: header is implicitly verified by SPF/DKIM).
 */
class CreateChannelVerifications extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('channel_verifications');
        $table->addColumn('channel', 'string', ['limit' => 30, 'null' => false])
              ->addColumn('external_identifier', 'string', [
                  'limit' => 255,
                  'null' => false,
                  'comment' => 'E.164 phone for WhatsApp/SMS, etc.',
              ])
              ->addColumn('code_hash', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('expires_at', 'datetime', ['null' => false])
              ->addColumn('attempts', 'integer', ['null' => false, 'default' => 0])
              ->addColumn('verified', 'boolean', ['null' => false, 'default' => false])
              ->addColumn('verified_at', 'datetime', ['null' => true, 'default' => null])
              ->addColumn('agent_id', 'integer', [
                  'null' => true,
                  'default' => null,
                  'comment' => 'Agent that received the inbound triggering verification',
              ])
              ->addColumn('pending_payload', 'text', [
                  'null' => true,
                  'default' => null,
                  'comment' => 'JSON-encoded original inbound; replayed once verification completes',
              ])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['channel', 'external_identifier', 'expires_at'], ['name' => 'idx_channel_verifications_lookup'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
              ->create();
    }
}
