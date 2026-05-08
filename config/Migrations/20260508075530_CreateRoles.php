<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRoles extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('roles');
        $table->addColumn('name', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('slug', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['slug'], ['unique' => true, 'name' => 'uq_roles_slug'])
              ->create();
    }
}
