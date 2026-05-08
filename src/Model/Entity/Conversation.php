<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Conversation extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
        'agent_id' => true,
        'title' => true,
        'source_text' => true,
        'status' => true,
        'blocks_found' => true,
        'blocks_processed' => true,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
}
