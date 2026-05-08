<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Model\Entity;

use Cake\ORM\Entity;

class IssueParsingJob extends Entity
{
    protected array $_accessible = [
        'conversation_id' => true,
        'agent_id' => true,
        'execution_id' => true,
        'raw_block' => true,
        'parsed_data' => true,
        'status' => true,
        'github_issue_number' => true,
        'github_issue_url' => true,
        'applied_labels' => true,
        'error_message' => true,
        'attempts' => true,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_CREATING = 'creating';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function getAppliedLabelsArray(): array
    {
        if (empty($this->applied_labels)) {
            return [];
        }
        $decoded = json_decode($this->applied_labels, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getParsedDataArray(): array
    {
        if (empty($this->parsed_data)) {
            return [];
        }
        $decoded = json_decode($this->parsed_data, true);
        return is_array($decoded) ? $decoded : [];
    }
}
