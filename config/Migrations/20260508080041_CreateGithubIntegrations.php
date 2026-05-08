<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateGithubIntegrations extends BaseMigration
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
        $table = $this->table('github_integrations');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('repo_owner', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('repo_name', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('token', 'text', ['null' => false, 'comment' => 'Encrypted GitHub PAT'])
              ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
              ->addColumn('last_used_at', 'datetime', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'], ['name' => 'idx_github_integrations_user_id'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
