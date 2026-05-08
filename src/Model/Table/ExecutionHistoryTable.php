<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class ExecutionHistoryTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('execution_history');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }

    public function findByAgent(SelectQuery $query, int $agentId): SelectQuery
    {
        return $query->where(['ExecutionHistory.agent_id' => $agentId])
                     ->orderBy(['ExecutionHistory.created' => 'DESC']);
    }
}
