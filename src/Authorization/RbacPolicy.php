<?php
declare(strict_types=1);

namespace App\Authorization;

use App\Model\Entity\User;
use Cake\ORM\TableRegistry;

class RbacPolicy
{
    /**
     * Cached permissions per role to avoid repeated DB hits per request.
     *
     * @var array<int, array<string, list<string>>>
     */
    private static array $cache = [];

    public function can(User $user, string $module, string $action): bool
    {
        $roleId = (int)$user->role_id;

        if (!isset(self::$cache[$roleId])) {
            self::$cache[$roleId] = $this->loadPermissions($roleId);
        }

        return isset(self::$cache[$roleId][$module]) &&
               in_array($action, self::$cache[$roleId][$module], true);
    }

    public function canRead(User $user, string $module): bool
    {
        return $this->can($user, $module, 'read');
    }

    public function canCreate(User $user, string $module): bool
    {
        return $this->can($user, $module, 'create');
    }

    public function canUpdate(User $user, string $module): bool
    {
        return $this->can($user, $module, 'update');
    }

    public function canDelete(User $user, string $module): bool
    {
        return $this->can($user, $module, 'delete');
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadPermissions(int $roleId): array
    {
        $permissions = TableRegistry::getTableLocator()
            ->get('Permissions')
            ->find()
            ->select(['module', 'action'])
            ->where(['role_id' => $roleId])
            ->all();

        $result = [];
        foreach ($permissions as $permission) {
            $result[$permission->module][] = $permission->action;
        }

        return $result;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
