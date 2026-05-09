<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Collapses the channel-specific guest roles (whatsapp_guest, slack_guest)
 * into a single channel-agnostic 'unregistered' role.
 *
 * The role represents "user was auto-created from an external channel and
 * has not been formally registered or approved yet" — the meaning is
 * identical regardless of which channel they arrived on. Channel
 * provenance lives in user_channel_identities, which is the right place
 * for that data; the role is just a permission bucket.
 *
 * Migration steps:
 *   1. Create the unregistered role if it does not already exist.
 *   2. Backfill user_channel_identities rows for existing whatsapp_guest
 *      users so the channel filter on the admin UI works uniformly across
 *      channels (Slack already populates the table from SlackOnboardingService).
 *   3. Reassign every user currently on whatsapp_guest / slack_guest to
 *      unregistered.
 *   4. Delete the old roles.
 */
class UnifyGuestRolesToUnregistered extends BaseMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();

        // 1. Ensure the unregistered role exists and resolve its id.
        $existing = $adapter->fetchAll("SELECT id FROM roles WHERE slug = 'unregistered'");
        if (count($existing) === 0) {
            $this->table('roles')->insert([[
                'slug' => 'unregistered',
                'name' => 'Unregistered',
                'description' => 'Auto-created when an external channel (WhatsApp, Slack, ...) message comes from a sender we have not registered yet. Zero platform permissions until approved by a superuser.',
                'created' => $now,
                'modified' => $now,
            ]])->saveData();
        }
        $unregisteredId = (int)$adapter->fetchAll("SELECT id FROM roles WHERE slug = 'unregistered'")[0]['id'];

        // 2. Backfill user_channel_identities for whatsapp_guest users.
        //    Slack guests already have rows from SlackOnboardingService.
        $whatsappGuests = $adapter->fetchAll(
            "SELECT u.id, u.phone_number, u.first_name, u.last_name "
            . "FROM users u JOIN roles r ON r.id = u.role_id "
            . "WHERE r.slug = 'whatsapp_guest' AND u.phone_number IS NOT NULL"
        );
        $identityInserts = [];
        foreach ($whatsappGuests as $row) {
            $identityInserts[] = [
                'user_id' => (int)$row['id'],
                'channel' => 'whatsapp',
                'external_id' => $row['phone_number'],
                'external_team_id' => null,
                'display_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: null,
                'email' => null,
                'verified_at' => $now,
                'created' => $now,
                'modified' => $now,
            ];
        }
        if ($identityInserts !== []) {
            $this->table('user_channel_identities')->insert($identityInserts)->saveData();
        }

        // 3. Reassign users to the unregistered role.
        $adapter->execute(
            "UPDATE users SET role_id = {$unregisteredId} "
            . "WHERE role_id IN (SELECT id FROM roles WHERE slug IN ('whatsapp_guest', 'slack_guest'))"
        );

        // 4. Drop the old roles.
        $adapter->execute("DELETE FROM roles WHERE slug IN ('whatsapp_guest', 'slack_guest')");
    }

    public function down(): void
    {
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();

        // Re-create the old roles. We can't reliably re-shard users back to
        // their original role since that information is lost — leave them on
        // unregistered and let the operator decide.
        $existing = $adapter->fetchAll("SELECT slug FROM roles WHERE slug IN ('whatsapp_guest', 'slack_guest')");
        $have = array_column($existing, 'slug');
        $inserts = [];
        if (!in_array('whatsapp_guest', $have, true)) {
            $inserts[] = [
                'slug' => 'whatsapp_guest',
                'name' => 'WhatsApp Guest',
                'description' => 'Recreated by migration rollback.',
                'created' => $now,
                'modified' => $now,
            ];
        }
        if (!in_array('slack_guest', $have, true)) {
            $inserts[] = [
                'slug' => 'slack_guest',
                'name' => 'Slack Guest',
                'description' => 'Recreated by migration rollback.',
                'created' => $now,
                'modified' => $now,
            ];
        }
        if ($inserts !== []) {
            $this->table('roles')->insert($inserts)->saveData();
        }
    }
}
