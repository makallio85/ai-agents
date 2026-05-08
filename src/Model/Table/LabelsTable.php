<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class LabelsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('labels');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->nonEmptyString('name')->maxLength('name', 100);
        $validator->nonEmptyString('slug')->maxLength('slug', 100);
        $validator->nonEmptyString('color')->maxLength('color', 7);
        return $validator;
    }
}
