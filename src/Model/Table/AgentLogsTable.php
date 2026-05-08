<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class AgentLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('agent_logs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }

    public function findByExecution(SelectQuery $query, string $executionId): SelectQuery
    {
        return $query->where(['AgentLogs.execution_id' => $executionId])
                     ->orderBy(['AgentLogs.created' => 'ASC']);
    }

    public function findByAgent(SelectQuery $query, int $agentId): SelectQuery
    {
        return $query->where(['AgentLogs.agent_id' => $agentId])
                     ->orderBy(['AgentLogs.created' => 'DESC']);
    }
}
