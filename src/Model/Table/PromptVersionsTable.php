<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class PromptVersionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('prompt_versions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }

    public function findActiveByAgent(SelectQuery $query, int $agentId): SelectQuery
    {
        return $query->where([
            'PromptVersions.agent_id' => $agentId,
            'PromptVersions.is_active' => true,
        ])->limit(1);
    }
}
