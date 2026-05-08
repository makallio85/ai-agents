<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AgentLog extends Entity
{
    protected array $_accessible = [
        'agent_id' => true,
        'execution_id' => true,
        'correlation_id' => true,
        'user_id' => true,
        'level' => true,
        'message' => true,
        'context' => true,
        'duration_ms' => true,
        'result_state' => true,
        'error_message' => true,
    ];

    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_DEBUG = 'debug';

    public const STATE_SUCCESS = 'success';
    public const STATE_FAILED = 'failed';
    public const STATE_PENDING = 'pending';
}
