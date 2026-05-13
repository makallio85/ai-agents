<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ORM table for agent_slack_configs.
 *
 * One row per agent. The unique index on agent_id enforces the one-to-one
 * relationship at the DB level. SlackConfigService is the only writer;
 * direct table access should be read-only from other layers.
 */
class AgentSlackConfigsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('agent_slack_configs');
        $this->setDisplayField('app_id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Agents', [
            'foreignKey' => 'agent_id',
            'joinType'   => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('agent_id');
        $validator->notEmptyString('app_id');
        $validator->notEmptyString('bot_token');
        $validator->notEmptyString('signing_secret');
        $validator->boolean('enabled');
        return $validator;
    }
}
