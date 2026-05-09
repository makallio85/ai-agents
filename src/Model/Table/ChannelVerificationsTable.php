<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ChannelVerificationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('channel_verifications');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Agents', ['foreignKey' => 'agent_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('channel', 'create')->notEmptyString('channel');
        $validator->requirePresence('external_identifier', 'create')->notEmptyString('external_identifier');
        $validator->requirePresence('code_hash', 'create')->notEmptyString('code_hash');
        $validator->requirePresence('expires_at', 'create');
        return $validator;
    }

    /**
     * Returns the active (unverified, unexpired) verification for a sender,
     * or null if none exists.
     *
     * @param SelectQuery<EntityInterface> $query
     * @return SelectQuery<EntityInterface>
     */
    public function findActive(SelectQuery $query, string $channel, string $externalIdentifier): SelectQuery
    {
        return $query->where([
            'ChannelVerifications.channel' => $channel,
            'ChannelVerifications.external_identifier' => $externalIdentifier,
            'ChannelVerifications.verified' => false,
            'ChannelVerifications.expires_at >' => new DateTime(),
        ])->orderByDesc('ChannelVerifications.created');
    }
}
