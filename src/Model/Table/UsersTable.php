<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('username');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Roles', [
            'foreignKey' => 'role_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('MfaTokens', [
            'foreignKey' => 'user_id',
            'dependent' => true,
        ]);

        $this->hasMany('GithubIntegrations', [
            'foreignKey' => 'user_id',
            'dependent' => true,
        ]);

        $this->hasMany('Conversations', [
            'foreignKey' => 'user_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->nonEmptyString('username')->maxLength('username', 100);
        $validator->email('email')->notEmptyString('email');
        $validator->notEmptyString('password')->minLength('password', 8);
        $validator->boolean('mfa_enabled');
        $validator->boolean('is_active');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']), ['errorField' => 'email']);
        $rules->add($rules->isUnique(['username']), ['errorField' => 'username']);
        $rules->add($rules->existsIn(['role_id'], 'Roles'));

        return $rules;
    }

    public function findActive(SelectQuery $query): SelectQuery
    {
        return $query->where(['Users.is_active' => true]);
    }
}
