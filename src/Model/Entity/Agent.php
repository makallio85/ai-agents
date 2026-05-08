<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Agent extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'slug' => true,
        'plugin' => true,
        'description' => true,
        'is_enabled' => true,
        'llm_provider' => true,
        'llm_model' => true,
        'instructions' => true,
        'config' => true,
    ];
}
