<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Slack-channel infrastructure.
 *
 * 1. user_channel_identities — persistent map from a channel's external user
 *    id (Slack user_id, future Telegram chat id, etc.) to a platform User.
 *    WhatsApp keyed off users.phone_number directly because phone numbers are
 *    a natural identifier; Slack's U02ABC123 user ids are opaque, so we need
 *    an explicit mapping table. Designed to grow: any new channel that
 *    needs identity persistence drops a row here.
 *
 * 2. slack_guest role — zero-permission role for users created from inbound
 *    Slack messages where the sender doesn't already exist on the platform.
 *    Mirrors whatsapp_guest. Approval gating is shared (users.is_approved).
 */
class CreateUserChannelIdentitiesAndSlackGuestRole extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('user_channel_identities');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('channel', 'string', ['limit' => 30, 'null' => false])
              ->addColumn('external_id', 'string', [
                  'limit' => 255,
                  'null' => false,
                  'comment' => "Channel-native user id (Slack U02ABC123, etc.)",
              ])
              ->addColumn('external_team_id', 'string', [
                  'limit' => 100,
                  'null' => true,
                  'default' => null,
                  'comment' => 'Slack workspace, Discord guild, etc. NULL when not applicable.',
              ])
              ->addColumn('display_name', 'string', ['limit' => 200, 'null' => true, 'default' => null])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => true, 'default' => null])
              ->addColumn('verified_at', 'datetime', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['channel', 'external_id'], [
                  'unique' => true,
                  'name' => 'uq_user_channel_identities_channel_extid',
              ])
              ->addIndex(['user_id'], ['name' => 'idx_user_channel_identities_user_id'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();

        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();
        $existing = $adapter->fetchAll("SELECT id FROM roles WHERE slug = 'slack_guest'");
        if (count($existing) === 0) {
            $this->table('roles')->insert([[
                'slug' => 'slack_guest',
                'name' => 'Slack Guest',
                'description' => 'Auto-created when an unrecognised Slack user messages a Slack-enabled agent. No platform permissions until approved.',
                'created' => $now,
                'modified' => $now,
            ]])->saveData();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM roles WHERE slug = 'slack_guest'");
        $this->table('user_channel_identities')->drop()->save();
    }
}
