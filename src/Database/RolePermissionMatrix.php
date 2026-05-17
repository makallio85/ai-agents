<?php
declare(strict_types=1);

namespace App\Database;

/**
 * Canonical role/permission matrix for the application.
 *
 * Single source of truth used by:
 *  - InitialDataSeed (fresh installs)
 *  - BackfillRolePermissions migration (existing deployments where the
 *    older EnsureRolePermissions migration ran before roles were seeded
 *    and therefore silently skipped its inserts)
 *  - Unit tests that assert the matrix covers every permission referenced
 *    in controller `requirePermission()` calls
 *
 * The matrix MUST stay in sync with every `$this->requirePermission(...)`
 * call in any `Api\V1\*Controller` — that is the live contract between
 * the controllers and the RBAC system. When a new endpoint introduces a
 * permission check, add the corresponding `(module, action)` here.
 */
final class RolePermissionMatrix
{
    /**
     * Return every (role-slug → module → list-of-actions) the application
     * needs in order for the standard roles to function.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function matrix(): array
    {
        $privileged = ['administrator', 'superuser'];
        $matrix = [];

        $crudModules = [
            'agents', 'chat', 'conversations', 'labels',
            'github_integrations', 'execution_history', 'prompt_versions',
        ];
        foreach ($privileged as $slug) {
            foreach ($crudModules as $module) {
                $matrix[$slug][$module] = ['read', 'create', 'update', 'delete'];
            }

            $matrix[$slug]['agent_logs'] = ['read'];
            $matrix[$slug]['roles'] = ['read', 'update'];
            // Extended chat actions on top of the CRUD baseline above.
            $matrix[$slug]['chat'] = array_merge(
                $matrix[$slug]['chat'],
                ['escalate', 'assign', 'configure']
            );
            // User-management actions (approve / reject pending signups,
            // list pending users) — these are the permissions whose absence
            // produced the 403s reported on PR #31.
            $matrix[$slug]['users'] = ['read', 'create', 'update', 'delete', 'list_pending', 'approve', 'reject'];
        }

        $matrix['user'] = [
            'agents' => ['read'],
            'chat' => ['read', 'create', 'escalate'],
            'conversations' => ['read', 'create'],
        ];

        return $matrix;
    }

    /**
     * Flatten the matrix into per-row tuples ready for SQL insertion.
     *
     * @param array<string, int> $roleIdBySlug map of role-slug → role.id
     * @return list<array{role_id: int, module: string, action: string}>
     */
    public static function rows(array $roleIdBySlug): array
    {
        $rows = [];
        foreach (self::matrix() as $slug => $modules) {
            if (!isset($roleIdBySlug[$slug])) {
                continue;
            }
            $roleId = $roleIdBySlug[$slug];
            foreach ($modules as $module => $actions) {
                foreach ($actions as $action) {
                    $rows[] = [
                        'role_id' => $roleId,
                        'module' => $module,
                        'action' => $action,
                    ];
                }
            }
        }

        return $rows;
    }
}
