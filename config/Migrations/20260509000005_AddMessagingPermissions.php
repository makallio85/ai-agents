<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * RBAC rows for the new messaging actions.
 *
 * Adds chat:escalate (any user can decide a session needs a human),
 * chat:assign (admin/superuser pick up sessions or hand them off),
 * and chat:configure (admin-only, manage per-agent channel credentials).
 *
 * Existing chat:create / read / update / delete already exist from
 * AddChatPermissions; we only insert the new rows here.
 */
class AddMessagingPermissions extends BaseMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();

        $rows = $adapter->fetchAll("SELECT id, slug FROM roles WHERE slug IN ('administrator', 'superuser', 'user')");
        $rolesBySlug = [];
        foreach ($rows as $row) {
            $rolesBySlug[$row['slug']] = (int)$row['id'];
        }

        $inserts = [];

        foreach (['administrator', 'superuser'] as $slug) {
            if (!isset($rolesBySlug[$slug])) {
                continue;
            }
            foreach (['escalate', 'assign', 'configure'] as $action) {
                $inserts[] = [
                    'role_id'  => $rolesBySlug[$slug],
                    'module'   => 'chat',
                    'action'   => $action,
                    'created'  => $now,
                    'modified' => $now,
                ];
            }
        }

        if (isset($rolesBySlug['user'])) {
            // Regular users may escalate their own sessions to a human, but cannot assign or configure.
            $inserts[] = [
                'role_id'  => $rolesBySlug['user'],
                'module'   => 'chat',
                'action'   => 'escalate',
                'created'  => $now,
                'modified' => $now,
            ];
        }

        if (!empty($inserts)) {
            $this->table('permissions')->insert($inserts)->saveData();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM permissions WHERE module = 'chat' AND action IN ('escalate', 'assign', 'configure')");
    }
}
