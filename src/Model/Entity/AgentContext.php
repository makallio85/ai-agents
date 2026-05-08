<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Represents a key-value context entry for an agent.
 *
 * Agent contexts are injected into the system prompt by LlmService so the
 * LLM has access to agent-specific configuration values (e.g. GitHub repo,
 * project name, deployment environment) without hardcoding them into
 * the instructions field.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $key
 * @property string $value
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\Agent $agent
 */
class AgentContext extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'agent_id' => true,
        'key' => true,
        'value' => true,
    ];
}
