<?php
declare(strict_types=1);

namespace App\Test\TestCase\Database;

use App\Authorization\RbacPolicy;
use App\Database\RolePermissionMatrix;
use App\Model\Entity\User;
use Cake\TestSuite\TestCase;

/**
 * Regression tests for the canonical role-permission matrix.
 *
 * Issue #9 review (PR #31) reported 403s on `users.list_pending`,
 * `message-channels` and similar endpoints. Root cause: the original
 * EnsureRolePermissions migration ran *before* the role seed on fresh
 * deploys and silently early-returned, so administrators ended up
 * missing the extended permission set even though they had role_id set
 * to administrator.
 *
 * These tests pin the matrix as the single source of truth and ensure
 * the next time someone adds a `requirePermission(...)` call in a
 * controller they will see a failing test if the matrix is not updated.
 */
class RolePermissionMatrixTest extends TestCase
{
    /** @var array<string> */
    protected array $fixtures = [
        'app.Roles',
        'app.Permissions',
    ];

    public function testAdministratorMatrixIncludesUserManagementActions(): void
    {
        $matrix = RolePermissionMatrix::matrix();

        $this->assertArrayHasKey('administrator', $matrix);
        $this->assertContains('list_pending', $matrix['administrator']['users']);
        $this->assertContains('approve', $matrix['administrator']['users']);
        $this->assertContains('reject', $matrix['administrator']['users']);
    }

    public function testAdministratorMatrixIncludesExtendedChatActions(): void
    {
        $matrix = RolePermissionMatrix::matrix();

        $this->assertContains('escalate', $matrix['administrator']['chat']);
        $this->assertContains('assign', $matrix['administrator']['chat']);
        $this->assertContains('configure', $matrix['administrator']['chat']);
    }

    public function testAdministratorMatrixIncludesReadonlyModules(): void
    {
        $matrix = RolePermissionMatrix::matrix();

        $this->assertSame(['read'], $matrix['administrator']['agent_logs']);
        $this->assertEqualsCanonicalizing(['read', 'update'], $matrix['administrator']['roles']);
    }

    public function testSuperuserMirrorsAdministrator(): void
    {
        $matrix = RolePermissionMatrix::matrix();
        $this->assertSame($matrix['administrator'], $matrix['superuser']);
    }

    public function testRowsSkipsUnknownRoles(): void
    {
        $rows = RolePermissionMatrix::rows(['administrator' => 1]);

        $roleIds = array_unique(array_column($rows, 'role_id'));
        $this->assertSame([1], $roleIds);
    }

    /**
     * RbacPolicy.can() must return true for every (module, action) in the
     * matrix when the permissions fixture has been loaded — guaranteeing
     * the fixture, the matrix and the policy agree.
     */
    public function testRbacPolicyAcceptsEveryMatrixEntryForAdministrator(): void
    {
        RbacPolicy::clearCache();

        $admin = new User(['id' => 1, 'role_id' => 1]);
        $policy = new RbacPolicy();
        $matrix = RolePermissionMatrix::matrix();

        foreach ($matrix['administrator'] as $module => $actions) {
            foreach ($actions as $action) {
                $this->assertTrue(
                    $policy->can($admin, $module, $action),
                    "Administrator should be granted {$module}.{$action}"
                );
            }
        }
    }
}
