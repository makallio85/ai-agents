<?php
declare(strict_types=1);

namespace App\Model\Table;

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
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('chat_session_id', 'create')->integer('chat_session_id');
        $validator->requirePresence('role', 'create')->inList('role', ['user', 'assistant', 'system']);
        $validator->requirePresence('content', 'create')->notEmptyString('content');
        $validator->allowEmptyString('tokens_used');
        $validator->allowEmptyString('model_used');
        return $validator;
    }
}
