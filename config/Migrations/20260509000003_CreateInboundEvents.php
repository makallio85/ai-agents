<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Stores raw inbound webhook payloads for any channel that pushes to us.
 *
 * Two purposes: (1) idempotency — a unique (channel, event_id) prevents
 * double-processing when providers retry; (2) audit — the raw payload is
 * preserved so we can replay or debug after the fact. Each row is later
 * picked up by ProcessInboundMessageJob, which delegates parsing to the
 * channel transport.
 */
class CreateInboundEvents extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('inbound_events');
        $table->addColumn('channel', 'string', [
                  'limit' => 30,
                  'null' => false,
                  'comment' => 'whatsapp|email|sms|...',
              ])
              ->addColumn('event_id', 'string', [
                  'limit' => 100,
                  'null' => false,
                  'comment' => "Provider's event id, or sha256 of payload as fallback",
              ])
              ->addColumn('external_account_id', 'string', [
                  'limit' => 100,
                  'null' => true,
                  'default' => null,
                  'comment' => 'WhatsApp phone_number_id, email mailbox, etc.',
              ])
              ->addColumn('signature_valid', 'boolean', [
                  'null' => false,
                  'default' => false,
              ])
              ->addColumn('payload', 'text', [
                  'null' => false,
                  'limit' => \Migrations\Db\Adapter\MysqlAdapter::TEXT_LONG,
              ])
              ->addColumn('processed_at', 'datetime', [
                  'null' => true,
                  'default' => null,
              ])
              ->addColumn('error_message', 'text', [
                  'null' => true,
                  'default' => null,
              ])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['channel', 'event_id'], [
                  'unique' => true,
                  'name' => 'uq_inbound_events_channel_event',
              ])
              ->addIndex(['channel', 'external_account_id'], ['name' => 'idx_inbound_events_channel_account'])
              ->addIndex(['processed_at'], ['name' => 'idx_inbound_events_processed_at'])
              ->create();
    }
}
