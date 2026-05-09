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
 * Channel and assignment fields support multi-channel routing (web, WhatsApp,
 * email, ...) and human handoff (agent -> pending_human -> human -> agent).
 *
 * @property int $id
 * @property int $user_id
 * @property int $agent_id
 * @property string $channel
 * @property string|null $channel_external_id
 * @property \Cake\I18n\DateTime|null $last_inbound_at
 * @property string $assignment_state
 * @property int|null $assigned_user_id
 * @property \Cake\I18n\DateTime|null $assigned_at
 * @property string|null $escalation_reason
 * @property string|null $title
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Agent $agent
 * @property \App\Model\Entity\User|null $assigned_user
 * @property \App\Model\Entity\ChatMessage[] $chat_messages
 */
class ChatSession extends Entity
{
    public const CHANNEL_WEB = 'web';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL = 'email';

    public const STATE_AGENT = 'agent';
    public const STATE_PENDING_HUMAN = 'pending_human';
    public const STATE_HUMAN = 'human';

    /** @var array<string, bool> */
    protected array $_accessible = [
        'user_id' => true,
        'agent_id' => true,
        'channel' => true,
        'channel_external_id' => true,
        'last_inbound_at' => true,
        'assignment_state' => true,
        'assigned_user_id' => true,
        'assigned_at' => true,
        'escalation_reason' => true,
        'title' => true,
        'user' => true,
        'agent' => true,
        'assigned_user' => true,
        'chat_messages' => true,
    ];

    public function isAgentHandled(): bool
    {
        return ($this->assignment_state ?? self::STATE_AGENT) === self::STATE_AGENT;
    }

    public function isHumanHandled(): bool
    {
        return ($this->assignment_state ?? '') === self::STATE_HUMAN;
    }

    public function isPendingHuman(): bool
    {
        return ($this->assignment_state ?? '') === self::STATE_PENDING_HUMAN;
    }
}
