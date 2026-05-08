<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMfaTokens extends BaseMigration
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
        $table = $this->table('mfa_tokens');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('token_hash', 'string', ['limit' => 255, 'null' => false, 'comment' => 'bcrypt hash of OTP token'])
              ->addColumn('expires_at', 'datetime', ['null' => false])
              ->addColumn('used', 'boolean', ['null' => false, 'default' => false])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'], ['name' => 'idx_mfa_tokens_user_id'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
