<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

class MfaTokensTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('mfa_tokens');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    public function findValidByUser(SelectQuery $query, int $userId): SelectQuery
    {
        return $query->where([
            'MfaTokens.user_id' => $userId,
            'MfaTokens.used' => false,
            'MfaTokens.expires_at >' => new DateTime(),
        ])->orderBy(['MfaTokens.created' => 'DESC']);
    }
}
