<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class PromptVersion extends Entity
{
    protected array $_accessible = [
        'agent_id' => true,
        'version' => true,
        'name' => true,
        'content' => true,
        'is_active' => true,
        'created_by' => true,
    ];
}
