<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePermissions extends BaseMigration
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
        $table = $this->table('permissions');
        $table->addColumn('role_id', 'integer', ['null' => false])
              ->addColumn('module', 'string', ['limit' => 100, 'null' => false, 'comment' => 'e.g. agents, conversations, users'])
              ->addColumn('action', 'string', ['limit' => 50, 'null' => false, 'comment' => 'read|create|update|delete'])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['role_id'], ['name' => 'idx_permissions_role_id'])
              ->addIndex(['role_id', 'module', 'action'], ['unique' => true, 'name' => 'uq_permissions_role_module_action'])
              ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
