<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class ExecutionHistory extends Entity
{
    protected array $_accessible = [
        'agent_id' => true,
        'user_id' => true,
        'execution_id' => true,
        'job_type' => true,
        'status' => true,
        'started_at' => true,
        'finished_at' => true,
        'result' => true,
        'error_message' => true,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
}
