<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Represents a single chat session between a user and an agent.
 *
 * A session groups an ordered sequence of ChatMessage records and can be
 * resumed at any time. The agent association carries the LLM provider and
 * model configuration needed to route requests to the correct backend.
 *
 * @property int $id
 * @property int $user_id
 * @property int $agent_id
 * @property string|null $title
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Agent $agent
 * @property \App\Model\Entity\ChatMessage[] $chat_messages
 */
class ChatSession extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'user_id' => true,
        'agent_id' => true,
        'title' => true,
        'user' => true,
        'agent' => true,
        'chat_messages' => true,
    ];
}
