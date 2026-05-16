<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates the agent_integration_permissions table.
 *
 * Stores one row per (agent, integration, action) triple, granting that agent
 * permission to perform the named action against the named integration. The
 * absence of a row means the agent does NOT have that permission (deny-all by
 * default). See AgentIntegrationPermissionService for enforcement logic and
 * the canonical list of supported actions.
 *
 * The unique index on (agent_id, integration, action) prevents duplicate
 * grants. agent_id is indexed independently for the common "all permissions
 * for agent X" lookup performed by the LLM agent loop on every request.
 */
class CreateAgentIntegrationPermissions extends BaseMigration
{
    public function change(): void
    {
        $this->table('agent_integration_permissions')
            ->addColumn('agent_id', 'integer', ['null' => false, 'limit' => 10])
            ->addColumn('integration', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('action', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addIndex(['agent_id', 'integration', 'action'], [
                'unique' => true,
                'name'   => 'uq_agent_integration_permissions',
            ])
            ->addIndex(['agent_id', 'integration'], ['name' => 'idx_agent_integration_permissions_agent_integration'])
            ->create();
    }
}
