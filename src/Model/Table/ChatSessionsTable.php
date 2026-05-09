<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ORM table for chat_sessions.
 *
 * Manages persistent conversation sessions between users and agents.
 * Sessions contain ordered ChatMessage records that form the conversation
 * history sent to the LLM on each turn.
 */
class ChatSessionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('chat_sessions');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
        $this->hasMany('ChatMessages', ['foreignKey' => 'chat_session_id', 'dependent' => true, 'sort' => ['ChatMessages.created' => 'ASC']]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('user_id', 'create')->integer('user_id');
        $validator->requirePresence('agent_id', 'create')->integer('agent_id');
        $validator->allowEmptyString('title');
        return $validator;
    }

    /**
     * Finds all sessions belonging to a specific user, ordered newest first,
     * with the associated agent eagerly loaded for display in the sidebar.
     *
     * @param SelectQuery<EntityInterface> $query
     * @return SelectQuery<EntityInterface>
     */
    public function findByUser(SelectQuery $query, int $userId): SelectQuery
    {
        return $query
            ->contain(['Agents'])
            ->where(['ChatSessions.user_id' => $userId])
            ->orderByDesc('ChatSessions.created');
    }
}
