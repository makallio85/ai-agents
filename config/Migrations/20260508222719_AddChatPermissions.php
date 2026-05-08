<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Inserts RBAC permission rows for the chat module.
 *
 * WHY: The chat feature (ChatController) gates every action behind
 * requirePermission('chat', ...). Without rows in the permissions table
 * the RBAC check always returns false → 403 Forbidden for all users.
 *
 * WHAT: Grants full CRUD to privileged roles (administrator, superuser) and
 * create+read to the base user role — matching the conversations pattern.
 * Role IDs are resolved by slug so the migration is safe across environments
 * (dev, test, production) where auto-increment IDs may differ.
 */
class AddChatPermissions extends BaseMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();

        // Resolve role IDs by slug — safe across environments
        $rows = $adapter->fetchAll("SELECT id, slug FROM roles WHERE slug IN ('administrator', 'superuser', 'user')");
        $rolesBySLug = [];
        foreach ($rows as $row) {
            $rolesBySLug[$row['slug']] = (int)$row['id'];
        }

        $inserts = [];

        foreach (['administrator', 'superuser'] as $slug) {
            if (!isset($rolesBySLug[$slug])) {
                continue;
            }
            foreach (['create', 'read', 'update', 'delete'] as $action) {
                $inserts[] = [
                    'role_id'  => $rolesBySLug[$slug],
                    'module'   => 'chat',
                    'action'   => $action,
                    'created'  => $now,
                    'modified' => $now,
                ];
            }
        }

        if (isset($rolesBySLug['user'])) {
            foreach (['create', 'read'] as $action) {
                $inserts[] = [
                    'role_id'  => $rolesBySLug['user'],
                    'module'   => 'chat',
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
        $this->execute("DELETE FROM permissions WHERE module = 'chat'");
    }
}
