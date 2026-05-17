<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use App\Database\RolePermissionMatrix;
use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for the permissions table.
 *
 * Sourced from the canonical {@see RolePermissionMatrix} so the fixture
 * cannot drift from the production seed/migration. Adds administrator
 * rows under role_id=1 (the id used by {@see RolesFixture}).
 */
class PermissionsFixture extends TestFixture
{
    public function init(): void
    {
        $now = '2026-01-01 00:00:00';

        $this->records = [];
        foreach (RolePermissionMatrix::rows(['administrator' => 1]) as $row) {
            $this->records[] = [
                'role_id' => $row['role_id'],
                'module' => $row['module'],
                'action' => $row['action'],
                'created' => $now,
                'modified' => $now,
            ];
        }

        parent::init();
    }
}
