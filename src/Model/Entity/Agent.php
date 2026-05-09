<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Represents a configured AI agent.
 *
 * An agent encapsulates an LLM provider binding (llm_provider + llm_model),
 * a system prompt (instructions), optional key-value context (agent_contexts),
 * and JSON-encoded config overrides. Agents are the central entity that all
 * chat, execution, and logging features reference.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $plugin
 * @property string|null $description
 * @property bool $is_enabled
 * @property string|null $llm_provider
 * @property string|null $llm_model
 * @property string|null $instructions
 * @property string|null $config
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\AgentContext[] $agent_contexts
 * @property \App\Model\Entity\AgentLog[] $agent_logs
 * @property \App\Model\Entity\PromptVersion[] $prompt_versions
 * @property \App\Model\Entity\Conversation[] $conversations
 * @property \App\Model\Entity\ChatSession[] $chat_sessions
 */
class Agent extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'name' => true,
        'slug' => true,
        'plugin' => true,
        'description' => true,
        'is_enabled' => true,
        'llm_provider' => true,
        'llm_model' => true,
        'instructions' => true,
        'config' => true,
    ];
}
