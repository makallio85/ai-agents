<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ChatMessage;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ORM table for chat_messages.
 *
 * Stores individual message turns within a chat session. Messages are
 * always ordered by creation time (ASC) to preserve conversation flow.
 */
class ChatMessagesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('chat_messages');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('ChatSessions', ['foreignKey' => 'chat_session_id']);
        $this->belongsTo('SenderUser', [
            'className' => 'Users',
            'foreignKey' => 'sender_user_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('chat_session_id', 'create')->integer('chat_session_id');
        $validator->requirePresence('role', 'create')->inList('role', [
            ChatMessage::ROLE_USER,
            ChatMessage::ROLE_ASSISTANT,
            ChatMessage::ROLE_SYSTEM,
        ]);
        $validator->requirePresence('content', 'create')->notEmptyString('content');
        $validator->allowEmptyString('tokens_used');
        $validator->allowEmptyString('model_used');
        $validator->inList('direction', [
            ChatMessage::DIRECTION_INBOUND,
            ChatMessage::DIRECTION_OUTBOUND,
        ]);
        $validator->inList('status', [
            ChatMessage::STATUS_RECEIVED,
            ChatMessage::STATUS_QUEUED,
            ChatMessage::STATUS_SENT,
            ChatMessage::STATUS_DELIVERED,
            ChatMessage::STATUS_READ,
            ChatMessage::STATUS_FAILED,
        ]);
        return $validator;
    }
}
