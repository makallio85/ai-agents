<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RolesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('roles');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('Users', ['foreignKey' => 'role_id']);
        $this->hasMany('Permissions', ['foreignKey' => 'role_id', 'dependent' => true]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->nonEmptyString('name')->maxLength('name', 100);
        $validator->nonEmptyString('slug')->maxLength('slug', 100);
        return $validator;
    }
}
