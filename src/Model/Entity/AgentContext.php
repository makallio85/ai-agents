<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AgentContext extends Entity
{
    protected array $_accessible = [
        'agent_id' => true,
        'key' => true,
        'value' => true,
    ];
}
