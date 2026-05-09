<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds approval gating for WhatsApp guest users and seeds the role they
 * land on by default.
 *
 * Background: when an unknown phone number messages a WhatsApp-enabled
 * agent, WhatsAppOnboardingService auto-creates a guest user after OTP.
 * Without approval gating, that stranger immediately gets whatever
 * permissions the fallback role has — risky as the platform grows.
 *
 * After this migration, new WhatsApp guests start with is_approved=false
 * and on the whatsapp_guest role (zero permissions). A superuser approves
 * them via the admin UI before any agent handler will run for their
 * messages. Existing users are backfilled to is_approved=true so the
 * change is invisible to them.
 *
 * approval_state ('pending'|'approved'|'rejected') stores the lifecycle;
 * is_approved is a redundant boolean kept for fast WHERE-clause filtering
 * and to keep call sites simple.
 */
class AddApprovalToUsersAndCreateWhatsappGuestRole extends BaseMigration
{
    public function up(): void
    {
        // Schema first
        $table = $this->table('users');
        $table->addColumn('is_approved', 'boolean', [
                  'null' => false,
                  'default' => true,
                  'after' => 'is_active',
                  'comment' => 'False for WhatsApp guests pending superuser approval',
              ])
              ->addColumn('approval_state', 'string', [
                  'limit' => 20,
                  'null' => false,
                  'default' => 'approved',
                  'after' => 'is_approved',
                  'comment' => 'pending|approved|rejected',
              ])
              ->addColumn('approved_by_user_id', 'integer', [
                  'null' => true,
                  'default' => null,
                  'after' => 'approval_state',
              ])
              ->addColumn('approved_at', 'datetime', [
                  'null' => true,
                  'default' => null,
                  'after' => 'approved_by_user_id',
              ])
              ->addIndex(['approval_state'], ['name' => 'idx_users_approval_state'])
              ->update();

        // whatsapp_guest role — zero permissions by default. Approved guests
        // get whatever the superuser opts them into later.
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();
        $existing = $adapter->fetchAll("SELECT id FROM roles WHERE slug = 'whatsapp_guest'");
        if (count($existing) === 0) {
            $this->table('roles')->insert([[
                'slug' => 'whatsapp_guest',
                'name' => 'WhatsApp Guest',
                'description' => 'Auto-created when an unknown phone number messages a WhatsApp-enabled agent. No platform permissions until approved.',
                'created' => $now,
                'modified' => $now,
            ]])->saveData();
        }

        // Approval permissions on the existing pattern (module='users', action=...).
        $rows = $adapter->fetchAll("SELECT id, slug FROM roles WHERE slug IN ('administrator', 'superuser')");
        $byslug = [];
        foreach ($rows as $row) {
            $byslug[$row['slug']] = (int)$row['id'];
        }

        $inserts = [];
        foreach (['administrator', 'superuser'] as $slug) {
            if (!isset($byslug[$slug])) {
                continue;
            }
            foreach (['approve', 'reject', 'list_pending'] as $action) {
                $inserts[] = [
                    'role_id'  => $byslug[$slug],
                    'module'   => 'users',
                    'action'   => $action,
                    'created'  => $now,
                    'modified' => $now,
                ];
            }
        }
        if (!empty($inserts)) {
            $this->table('permissions')->insert($inserts)->saveData();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM permissions WHERE module = 'users' AND action IN ('approve', 'reject', 'list_pending')");
        $this->execute("DELETE FROM roles WHERE slug = 'whatsapp_guest'");
        $table = $this->table('users');
        $table->removeIndexByName('idx_users_approval_state')
              ->removeColumn('is_approved')
              ->removeColumn('approval_state')
              ->removeColumn('approved_by_user_id')
              ->removeColumn('approved_at')
              ->update();
    }
}
