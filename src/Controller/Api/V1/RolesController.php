<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

/**
 * API controller for roles and their permission matrices.
 *
 * Exposes the roles list (with eager-loaded permissions) and a bulk-replace
 * endpoint so the permissions UI can save the full matrix for a role in one
 * request. Only administrators and superusers reach these endpoints via the
 * roles.read / roles.update permission gates.
 */
class RolesController extends AppController
{
    /**
     * GET /api/v1/roles
     *
     * Returns all roles with their current permissions.
     * Each role carries a flat `permissions` array of {module, action} objects
     * so the Vue matrix can build its checked-state map without further requests.
     */
    public function index(): void
    {
        $this->requirePermission('roles', 'read');

        $roles = $this->fetchTable('Roles')
            ->find()
            ->contain(['Permissions'])
            ->orderByAsc('Roles.id')
            ->all()
            ->toList();

        $this->success($roles);
    }

    /**
     * POST /api/v1/roles/update-permissions/:id
     *
     * Replaces the full permission set for a role in one atomic operation.
     *
     * Body: { permissions: [ { module: string, action: string }, … ] }
     *
     * All existing permissions for the role are deleted first, then the
     * submitted list is inserted. This avoids diffing logic and keeps the
     * implementation simple — the matrix always sends the complete desired state.
     *
     * Why bulk-replace: the UI works as a full matrix snapshot, not a diff.
     * Sending only deltas would require the server to reason about removals,
     * which adds complexity for no practical benefit at this scale.
     */
    public function updatePermissions(int $id): void
    {
        $this->requirePermission('roles', 'update');

        $rolesTable = $this->fetchTable('Roles');
        $role = $rolesTable->find()->where(['Roles.id' => $id])->first();

        if ($role === null) {
            $this->error('Role not found', [], 404);
            return;
        }

        $incoming = $this->request->getData('permissions');
        if (!is_array($incoming)) {
            $this->error('permissions must be an array', [], 422);
            return;
        }

        $permissionsTable = $this->fetchTable('Permissions');

        // Validate all entries before touching the database
        $validActions = ['read', 'create', 'update', 'delete', 'approve', 'reject', 'list_pending', 'configure', 'escalate', 'assign'];
        foreach ($incoming as $perm) {
            if (empty($perm['module']) || empty($perm['action'])) {
                $this->error('Each permission must have module and action', [], 422);
                return;
            }
            if (!in_array($perm['action'], $validActions, true)) {
                $this->error("Invalid action: {$perm['action']}", [], 422);
                return;
            }
        }

        // Atomic replace: delete all existing, insert new set
        $permissionsTable->deleteAll(['role_id' => $id]);

        if (!empty($incoming)) {
            $entities = [];
            foreach ($incoming as $perm) {
                $entities[] = $permissionsTable->newEntity([
                    'role_id' => $id,
                    'module'  => $perm['module'],
                    'action'  => $perm['action'],
                ]);
            }
            $permissionsTable->saveMany($entities);
        }

        // Return the updated role with fresh permissions
        $updated = $rolesTable->find()->contain(['Permissions'])->where(['Roles.id' => $id])->first();
        $this->success($updated);
    }
}
