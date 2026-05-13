<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ORM table for agent_whatsapp_configs.
 *
 * One row per agent. The unique index on agent_id enforces the one-to-one
 * relationship at the DB level. WhatsAppConfigService is the only writer.
 */
class AgentWhatsAppConfigsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('agent_whatsapp_configs');
        $this->setDisplayField('phone_number_id');
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
        $validator->notEmptyString('phone_number_id');
        $validator->notEmptyString('access_token');
        $validator->boolean('enabled');
        return $validator;
    }
}
