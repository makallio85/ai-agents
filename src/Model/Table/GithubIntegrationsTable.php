<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class GithubIntegrationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('github_integrations');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->nonEmptyString('repo_owner')->maxLength('repo_owner', 150);
        $validator->nonEmptyString('repo_name')->maxLength('repo_name', 150);
        $validator->notEmptyString('token');
        return $validator;
    }

    public function findActiveByUser(SelectQuery $query, int $userId): SelectQuery
    {
        return $query->where([
            'GithubIntegrations.user_id' => $userId,
            'GithubIntegrations.is_active' => true,
        ])->orderBy(['GithubIntegrations.created' => 'DESC']);
    }
}
