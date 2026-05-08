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
 * WHAT: Grants full CRUD to Administrator and Superuser roles, and
 * create+read to the base User role (matching the conversations pattern).
 */
class AddChatPermissions extends BaseMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [];

        // Administrator (1) and Superuser (2) — full access
        foreach ([1, 2] as $roleId) {
            foreach (['create', 'read', 'update', 'delete'] as $action) {
                $rows[] = [
                    'role_id' => $roleId,
                    'module'  => 'chat',
                    'action'  => $action,
                    'created' => $now,
                    'modified' => $now,
                ];
            }
        }

        // User (3) — create + read only
        foreach (['create', 'read'] as $action) {
            $rows[] = [
                'role_id' => 3,
                'module'  => 'chat',
                'action'  => $action,
                'created' => $now,
                'modified' => $now,
            ];
        }

        $this->table('permissions')->insert($rows)->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM permissions WHERE module = 'chat'");
    }
}
