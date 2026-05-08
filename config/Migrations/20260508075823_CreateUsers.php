<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUsers extends BaseMigration
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
        $table = $this->table('users');
        $table->addColumn('role_id', 'integer', ['null' => false])
              ->addColumn('username', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('first_name', 'string', ['limit' => 100, 'null' => true, 'default' => null])
              ->addColumn('last_name', 'string', ['limit' => 100, 'null' => true, 'default' => null])
              ->addColumn('phone_number', 'string', ['limit' => 30, 'null' => true, 'default' => null])
              ->addColumn('mfa_enabled', 'boolean', ['null' => false, 'default' => false])
              ->addColumn('mfa_secret', 'text', ['null' => true, 'default' => null, 'comment' => 'Encrypted'])
              ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
              ->addColumn('last_login_at', 'datetime', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['role_id'], ['name' => 'idx_users_role_id'])
              ->addIndex(['email'], ['unique' => true, 'name' => 'uq_users_email'])
              ->addIndex(['username'], ['unique' => true, 'name' => 'uq_users_username'])
              ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
              ->create();
    }
}
