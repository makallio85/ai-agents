<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ORM entity for agent_slack_configs.
 *
 * Stores the per-agent Slack App credentials. bot_token and signing_secret
 * are encrypted at rest by SlackConfigService before being written here.
 * Direct access to these fields always returns the encrypted value — callers
 * must go through SlackConfigService::findConfigByAgentId() to get decrypted
 * values inside a SlackAgentConfig DTO.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $app_id
 * @property string $bot_user_id
 * @property string $bot_token
 * @property string $signing_secret
 * @property string|null $team_id
 * @property bool $enabled
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\Agent $agent
 */
class AgentSlackConfig extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'agent_id'       => true,
        'app_id'         => true,
        'bot_user_id'    => true,
        'bot_token'      => true,
        'signing_secret' => true,
        'team_id'        => true,
        'enabled'        => true,
        'agent'          => true,
    ];
}
