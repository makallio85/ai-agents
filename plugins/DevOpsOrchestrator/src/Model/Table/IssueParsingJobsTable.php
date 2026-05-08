<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class IssueParsingJobsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('issue_parsing_jobs');
        $this->setEntityClass(\DevOpsOrchestrator\Model\Entity\IssueParsingJob::class);
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Conversations', [
            'foreignKey' => 'conversation_id',
            'className' => 'App.Conversations',
        ]);
        $this->belongsTo('Agents', [
            'foreignKey' => 'agent_id',
            'className' => 'App.Agents',
        ]);
    }

    public function findByConversation(SelectQuery $query, int $conversationId): SelectQuery
    {
        return $query->where(['IssueParsingJobs.conversation_id' => $conversationId])
                     ->orderBy(['IssueParsingJobs.created' => 'ASC']);
    }
}
