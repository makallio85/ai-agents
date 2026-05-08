<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class AgentContextsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('agent_contexts');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }
}
