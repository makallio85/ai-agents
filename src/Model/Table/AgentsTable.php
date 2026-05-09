<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class AgentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('agents');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('AgentContexts', ['foreignKey' => 'agent_id', 'dependent' => true]);
        $this->hasMany('AgentLogs', ['foreignKey' => 'agent_id']);
        $this->hasMany('ExecutionHistory', ['foreignKey' => 'agent_id']);
        $this->hasMany('PromptVersions', ['foreignKey' => 'agent_id', 'dependent' => true]);
        $this->hasMany('Conversations', ['foreignKey' => 'agent_id']);
        $this->hasMany('ChatSessions', ['foreignKey' => 'agent_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('name')->maxLength('name', 150);
        $validator->notEmptyString('slug')->maxLength('slug', 150);
        $validator->notEmptyString('plugin')->maxLength('plugin', 150);
        $validator->boolean('is_enabled');
        return $validator;
    }

    public function findEnabled(SelectQuery $query): SelectQuery
    {
        return $query->where(['Agents.is_enabled' => true]);
    }
}
