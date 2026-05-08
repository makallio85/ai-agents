<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ConversationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('conversations');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('source_text');
        $validator->inList('status', ['pending', 'processing', 'completed', 'failed']);
        return $validator;
    }

    public function findByUser(SelectQuery $query, int $userId): SelectQuery
    {
        return $query->where(['Conversations.user_id' => $userId])
                     ->orderBy(['Conversations.created' => 'DESC']);
    }
}
