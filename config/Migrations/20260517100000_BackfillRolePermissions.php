<?php
declare(strict_types=1);

use App\Database\RolePermissionMatrix;
use Migrations\BaseMigration;

/**
 * Idempotently backfills role/permission rows from the canonical matrix.
 *
 * WHY THIS EXISTS: the earlier EnsureRolePermissions migration (20260512000002)
 * resolved role ids at migration time, and on every fresh deploy the role
 * seed runs AFTER migrations. So that migration found an empty roles table
 * and silently early-returned, leaving administrators missing
 * `users.list_pending`, `users.approve`, `users.reject`, `chat.escalate`,
 * `chat.assign`, `chat.configure`, `agent_logs.read`, `roles.read` and
 * `roles.update` — the permissions reported as broken on PR #31.
 *
 * This migration is the recovery hook for existing previews/production:
 * on the *next* `bin/cake migrations migrate` after the role seed has
 * already populated the roles table, this fires and adds the missing
 * rows under INSERT IGNORE so it stays safe to re-run.
 *
 * NEW INSTALLS: rely on InitialDataSeed (which now uses the same matrix)
 * to seed the full set up front. This migration is a no-op on those
 * because every row already exists.
 */
class BackfillRolePermissions extends BaseMigration
{
    public function up(): void
    {
        $adapter = $this->getAdapter();
        $now = date('Y-m-d H:i:s');

        // Slugs are hard-coded constants from the role catalog, so direct
        // interpolation is safe and matches the style used by the original
        // EnsureRolePermissions migration.
        $rows = $adapter->fetchAll(
            "SELECT id, slug FROM roles WHERE slug IN ('administrator', 'superuser', 'user', 'unregistered')"
        );

        if (empty($rows)) {
            // No roles yet — seeds have not run. The migration is marked
            // complete but did nothing; operators must re-run this migration
            // after seeds via `bin/cake migrations migrate --target=20260517100000`
            // or rely on the seed itself which already inserts the full
            // matrix on fresh installs.
            return;
        }

        $roleIdBySlug = [];
        foreach ($rows as $row) {
            $roleIdBySlug[$row['slug']] = (int)$row['id'];
        }

        foreach (RolePermissionMatrix::rows($roleIdBySlug) as $row) {
            $adapter->execute(
                'INSERT IGNORE INTO permissions (role_id, module, action, created, modified) '
                . 'VALUES (?, ?, ?, ?, ?)',
                [$row['role_id'], $row['module'], $row['action'], $now, $now]
            );
        }
    }

    public function down(): void
    {
        // Intentionally empty — rolling back permission grants is destructive
        // and is best resolved by restoring from a database backup.
    }
}
