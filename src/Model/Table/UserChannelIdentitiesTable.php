<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UserChannelIdentitiesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('user_channel_identities');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('user_id', 'create')->integer('user_id');
        $validator->requirePresence('channel', 'create')->notEmptyString('channel');
        $validator->requirePresence('external_id', 'create')->notEmptyString('external_id');
        return $validator;
    }

    /**
     * @param SelectQuery<EntityInterface> $query
     * @return SelectQuery<EntityInterface>
     */
    public function findByExternal(SelectQuery $query, string $channel, string $externalId): SelectQuery
    {
        return $query
            ->contain(['Users'])
            ->where([
                'UserChannelIdentities.channel' => $channel,
                'UserChannelIdentities.external_id' => $externalId,
            ]);
    }
}
