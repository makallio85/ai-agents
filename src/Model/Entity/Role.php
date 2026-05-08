<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Role extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'slug' => true,
        'description' => true,
    ];

    public const ADMINISTRATOR = 'administrator';
    public const SUPERUSER = 'superuser';
    public const USER = 'user';
}
