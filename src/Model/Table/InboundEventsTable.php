<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class InboundEventsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('inbound_events');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('channel', 'create')->notEmptyString('channel');
        $validator->requirePresence('event_id', 'create')->notEmptyString('event_id');
        $validator->requirePresence('payload', 'create')->notEmptyString('payload');
        $validator->boolean('signature_valid');
        return $validator;
    }
}
