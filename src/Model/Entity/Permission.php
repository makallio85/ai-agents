<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Permission extends Entity
{
    protected array $_accessible = [
        'role_id' => true,
        'module' => true,
        'action' => true,
    ];

    public const ACTION_READ = 'read';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
}
