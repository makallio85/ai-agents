<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ChatSession;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use RuntimeException;

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
        $this->belongsTo('AssignedUser', [
            'className' => 'Users',
            'foreignKey' => 'assigned_user_id',
        ]);
        $this->hasMany('ChatMessages', ['foreignKey' => 'chat_session_id', 'dependent' => true, 'sort' => ['ChatMessages.created' => 'ASC']]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('user_id', 'create')->integer('user_id');
        $validator->requirePresence('agent_id', 'create')->integer('agent_id');
        $validator->allowEmptyString('title');
        $validator->inList('channel', [
            ChatSession::CHANNEL_WEB,
            ChatSession::CHANNEL_WHATSAPP,
            ChatSession::CHANNEL_SLACK,
            ChatSession::CHANNEL_EMAIL,
        ], 'channel must be a recognised value');
        $validator->inList('assignment_state', [
            ChatSession::STATE_AGENT,
            ChatSession::STATE_PENDING_HUMAN,
            ChatSession::STATE_HUMAN,
        ], 'assignment_state must be a recognised value');
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

    /**
     * Finds sessions awaiting human pickup, optionally filtered to a specific
     * assignee. Used by the inbox sidebar.
     *
     * @param SelectQuery<EntityInterface> $query
     * @return SelectQuery<EntityInterface>
     */
    public function findPendingHuman(SelectQuery $query, ?int $assignedUserId = null): SelectQuery
    {
        $conditions = ['ChatSessions.assignment_state' => ChatSession::STATE_PENDING_HUMAN];
        if ($assignedUserId !== null) {
            $conditions['ChatSessions.assigned_user_id IS'] = $assignedUserId;
        }
        return $query
            ->contain(['Agents', 'Users'])
            ->where($conditions)
            ->orderByDesc('ChatSessions.last_inbound_at');
    }

    /**
     * Returns the existing session for (user, agent, channel, channel_external_id),
     * or creates one. Used by inbound transports to land messages on a stable
     * conversation thread.
     *
     * @throws RuntimeException If the new session cannot be persisted.
     */
    public function findOrCreateForChannel(
        int $userId,
        int $agentId,
        string $channel,
        ?string $externalId,
    ): ChatSession {
        $conditions = [
            'ChatSessions.user_id' => $userId,
            'ChatSessions.agent_id' => $agentId,
            'ChatSessions.channel' => $channel,
        ];
        if ($externalId !== null) {
            $conditions['ChatSessions.channel_external_id'] = $externalId;
        } else {
            $conditions['ChatSessions.channel_external_id IS'] = null;
        }

        /** @var ChatSession|null $existing */
        $existing = $this->find()
            ->where($conditions)
            ->orderByDesc('ChatSessions.created')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $entity = $this->newEntity([
            'user_id' => $userId,
            'agent_id' => $agentId,
            'channel' => $channel,
            'channel_external_id' => $externalId,
            'assignment_state' => ChatSession::STATE_AGENT,
        ]);
        if (!$this->save($entity)) {
            throw new RuntimeException('Failed to create chat session: ' . json_encode($entity->getErrors()));
        }
        /** @var ChatSession $entity */
        return $entity;
    }
}
