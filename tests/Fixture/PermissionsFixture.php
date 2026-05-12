<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for the permissions table.
 *
 * Seeds the complete expected permissions matrix for role id=1 (administrator)
 * as defined by EnsureRolePermissions migration. Tests that verify the RBAC
 * invariant rely on these rows being present without needing migrations to run.
 *
 * role_id=1 matches RolesFixture administrator entry.
 */
class PermissionsFixture extends TestFixture
{
    public function init(): void
    {
        $now = '2026-01-01 00:00:00';
        $adminId = 1; // matches RolesFixture

        $crudModules = [
            'agents', 'chat', 'conversations', 'labels',
            'github_integrations', 'execution_history', 'prompt_versions',
        ];

        $this->records = [];

        foreach ($crudModules as $module) {
            foreach (['read', 'create', 'update', 'delete'] as $action) {
                $this->records[] = [
                    'role_id' => $adminId, 'module' => $module,
                    'action' => $action, 'created' => $now, 'modified' => $now,
                ];
            }
        }

        foreach (['read'] as $action) {
            $this->records[] = [
                'role_id' => $adminId, 'module' => 'agent_logs',
                'action' => $action, 'created' => $now, 'modified' => $now,
            ];
        }

        foreach (['read', 'update'] as $action) {
            $this->records[] = [
                'role_id' => $adminId, 'module' => 'roles',
                'action' => $action, 'created' => $now, 'modified' => $now,
            ];
        }

        foreach (['escalate', 'assign', 'configure'] as $action) {
            $this->records[] = [
                'role_id' => $adminId, 'module' => 'chat',
                'action' => $action, 'created' => $now, 'modified' => $now,
            ];
        }

        foreach (['list_pending', 'approve', 'reject'] as $action) {
            $this->records[] = [
                'role_id' => $adminId, 'module' => 'users',
                'action' => $action, 'created' => $now, 'modified' => $now,
            ];
        }

        parent::init();
    }
}
