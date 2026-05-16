<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ORM table for agent_integration_permissions.
 *
 * Stores the (agent, integration, action) grants that drive per-agent
 * integration access. Used by AgentIntegrationPermissionService to load
 * the permission set for an agent at the start of an LLM turn and by the
 * permissions management UI / API to read and write grants.
 *
 * The deny-all default is enforced at the service layer (no row = no
 * permission); this table only persists rows. A unique index on
 * (agent_id, integration, action) prevents duplicate grants.
 */
class AgentIntegrationPermissionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('agent_integration_permissions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('agent_id')
            ->notEmptyString('agent_id')
            ->notEmptyString('integration')->maxLength('integration', 100)
            ->notEmptyString('action')->maxLength('action', 150);

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(
            ['agent_id', 'integration', 'action'],
            'This permission already exists for the agent.',
        ));
        $rules->add($rules->existsIn(['agent_id'], 'Agents'));

        return $rules;
    }

    /**
     * Returns every permission grant for the given agent.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface> $query
     * @return \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface>
     */
    public function findForAgent(SelectQuery $query, int $agentId): SelectQuery
    {
        return $query->where(['AgentIntegrationPermissions.agent_id' => $agentId]);
    }
}
