<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PermissionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('permissions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Roles', ['foreignKey' => 'role_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->nonEmptyString('module');
        $validator->inList('action', ['read', 'create', 'update', 'delete']);
        return $validator;
    }
}
