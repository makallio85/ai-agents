<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Idempotently ensures all required permission rows exist for every role.
 *
 * WHY THIS EXISTS: AddChatPermissions and AddMessagingPermissions migrations
 * resolve role IDs at migration time. On a fresh deploy where InitialDataSeed
 * runs AFTER migrations (the standard production order), the roles table is
 * empty during those migrations and all INSERT statements are silently skipped.
 * This leaves administrators missing chat.escalate, chat.assign, chat.configure
 * and other critical permissions.
 *
 * This migration is the authoritative, idempotent source of truth for the
 * complete permissions matrix. It uses INSERT IGNORE so it is safe to run
 * on any environment regardless of which rows already exist.
 *
 * MATRIX:
 *   administrator + superuser:
 *     agents:              read, create, update, delete
 *     chat:                read, create, update, delete, escalate, assign, configure
 *     conversations:       read, create, update, delete
 *     labels:              read, create, update, delete
 *     github_integrations: read, create, update, delete
 *     agent_logs:          read
 *     execution_history:   read
 *     prompt_versions:     read
 *     roles:               read, update
 *     users:               list_pending, approve, reject
 *
 *   user:
 *     agents:        read
 *     chat:          read, create, escalate
 *     conversations: read, create
 */
class EnsureRolePermissions extends BaseMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $adapter = $this->getAdapter();

        $rows = $adapter->fetchAll(
            "SELECT id, slug FROM roles WHERE slug IN ('administrator', 'superuser', 'user', 'unregistered')"
        );
        if (empty($rows)) {
            // Roles not seeded yet — nothing to do; InitialDataSeed will handle
            // the base permissions and this migration is safe to re-run later
            // via bin/cake migrations migrate (it is idempotent via INSERT IGNORE).
            return;
        }

        $bySlug = [];
        foreach ($rows as $row) {
            $bySlug[$row['slug']] = (int)$row['id'];
        }

        $inserts = $this->buildMatrix($bySlug, $now);

        if (empty($inserts)) {
            return;
        }

        // INSERT IGNORE skips rows that violate the unique index
        // (role_id, module, action) — safe to run repeatedly.
        foreach ($inserts as $row) {
            $adapter->execute(
                "INSERT IGNORE INTO permissions (role_id, module, action, created, modified) VALUES (?, ?, ?, ?, ?)",
                [$row['role_id'], $row['module'], $row['action'], $row['created'], $row['modified']]
            );
        }
    }

    public function down(): void
    {
        // Intentionally left empty — removing permissions in a rollback is
        // destructive and not recoverable. If a rollback is needed, restore
        // from a database backup.
    }

    /**
     * Builds the complete expected permissions matrix.
     *
     * @param array<string, int> $bySlug
     * @param string $now
     * @return list<array{role_id: int, module: string, action: string, created: string, modified: string}>
     */
    private function buildMatrix(array $bySlug, string $now): array
    {
        $inserts = [];

        $privileged = array_filter(
            ['administrator', 'superuser'],
            fn(string $s) => isset($bySlug[$s])
        );

        // Full CRUD modules for admin + superuser
        $crudModules = [
            'agents', 'chat', 'conversations', 'labels',
            'github_integrations', 'execution_history', 'prompt_versions',
        ];
        foreach ($crudModules as $module) {
            foreach (['read', 'create', 'update', 'delete'] as $action) {
                foreach ($privileged as $slug) {
                    $inserts[] = $this->row($bySlug[$slug], $module, $action, $now);
                }
            }
        }

        // Read-only modules for admin + superuser
        foreach (['agent_logs'] as $module) {
            foreach ($privileged as $slug) {
                $inserts[] = $this->row($bySlug[$slug], $module, 'read', $now);
            }
        }

        // Roles module for admin + superuser
        foreach (['read', 'update'] as $action) {
            foreach ($privileged as $slug) {
                $inserts[] = $this->row($bySlug[$slug], 'roles', $action, $now);
            }
        }

        // Extended chat actions for admin + superuser
        foreach (['escalate', 'assign', 'configure'] as $action) {
            foreach ($privileged as $slug) {
                $inserts[] = $this->row($bySlug[$slug], 'chat', $action, $now);
            }
        }

        // User management for admin + superuser
        foreach (['list_pending', 'approve', 'reject'] as $action) {
            foreach ($privileged as $slug) {
                $inserts[] = $this->row($bySlug[$slug], 'users', $action, $now);
            }
        }

        // user role — limited access
        if (isset($bySlug['user'])) {
            $userId = $bySlug['user'];
            foreach ([
                ['agents', 'read'],
                ['chat', 'read'],
                ['chat', 'create'],
                ['chat', 'escalate'],
                ['conversations', 'read'],
                ['conversations', 'create'],
            ] as [$module, $action]) {
                $inserts[] = $this->row($userId, $module, $action, $now);
            }
        }

        return $inserts;
    }

    /**
     * @return array{role_id: int, module: string, action: string, created: string, modified: string}
     */
    private function row(int $roleId, string $module, string $action, string $now): array
    {
        return [
            'role_id'  => $roleId,
            'module'   => $module,
            'action'   => $action,
            'created'  => $now,
            'modified' => $now,
        ];
    }
}
