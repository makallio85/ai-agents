<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Represents a single grant of a named action against an integration for an agent.
 *
 * Each row authorises one agent to perform one specific action (e.g.
 * `github.issues.write`) against one integration (e.g. `github`). The
 * absence of a row means the agent has NOT been granted that permission,
 * and the action MUST be denied (deny-all by default — see
 * AgentIntegrationPermissionService).
 *
 * The persistence model is intentionally flat: there are no roles or
 * groups. Administrators grant individual (agent, integration, action)
 * triples through the agent's Permissions tab; permissions are looked up
 * once at the start of every agent turn and cached for the duration of
 * that request.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $integration
 * @property string $action
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\Agent $agent
 */
class AgentIntegrationPermission extends Entity
{
    /** @var array<string, bool> */
    protected array $_accessible = [
        'agent_id' => true,
        'integration' => true,
        'action' => true,
    ];
}
