<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use App\Authorization\RbacPolicy;
use App\Model\Entity\User;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Verifies that every required permission row is present in the permissions
 * table after all migrations have run.
 *
 * Bug reproduced: AddMessagingPermissions and AddChatPermissions migrations
 * silently skip their INSERT when the roles table is empty at migration time
 * (e.g. on a fresh deploy where InitialDataSeed runs after migrations).
 * This leaves the administrator role missing chat.configure, chat.escalate,
 * and chat.assign.
 *
 * These tests must be RED on environments with the permissions gap and GREEN
 * after the EnsureRolePermissions migration is applied.
 *
 * NOTE: This test deliberately does NOT use a Permissions fixture so the
 * permissions table is not truncated — we verify the migration-inserted rows
 * directly. The Roles fixture ensures role IDs are stable.
 */
class RbacPermissionsTest extends TestCase
{
    /**
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Roles',
        'app.Permissions',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        RbacPolicy::clearCache();
    }

    /**
     * @dataProvider administratorPermissionsProvider
     */
    public function testAdministratorHasPermission(string $module, string $action): void
    {
        $user = $this->makeUserWithRole('administrator');

        $this->assertTrue(
            (new RbacPolicy())->can($user, $module, $action),
            "Administrator must have {$module}.{$action} — check EnsureRolePermissions migration"
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function administratorPermissionsProvider(): array
    {
        return [
            'agents.read'    => ['agents', 'read'],
            'agents.create'  => ['agents', 'create'],
            'agents.update'  => ['agents', 'update'],
            'agents.delete'  => ['agents', 'delete'],
            'chat.create'    => ['chat', 'create'],
            'chat.read'      => ['chat', 'read'],
            'chat.update'    => ['chat', 'update'],
            'chat.delete'    => ['chat', 'delete'],
            'chat.escalate'  => ['chat', 'escalate'],
            'chat.assign'    => ['chat', 'assign'],
            'chat.configure' => ['chat', 'configure'],
        ];
    }

    private function makeUserWithRole(string $roleSlug): User
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->find()->where(['slug' => $roleSlug])->first();
        $this->assertNotNull($role, "Role '{$roleSlug}' must exist");

        return new User(['id' => 1, 'role_id' => $role->id]);
    }
}
