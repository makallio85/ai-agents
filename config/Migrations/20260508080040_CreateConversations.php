<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateConversations extends BaseMigration
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
        $table = $this->table('conversations');
        $table->addColumn('user_id', 'integer', ['null' => false])
              ->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('title', 'string', ['limit' => 255, 'null' => true, 'default' => null])
              ->addColumn('source_text', 'text', ['null' => false, 'limit' => \Migrations\Db\Adapter\MysqlAdapter::TEXT_LONG, 'comment' => 'Raw pasted conversation'])
              ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending', 'comment' => 'pending|processing|completed|failed'])
              ->addColumn('blocks_found', 'integer', ['null' => false, 'default' => 0])
              ->addColumn('blocks_processed', 'integer', ['null' => false, 'default' => 0])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'], ['name' => 'idx_conversations_user_id'])
              ->addIndex(['agent_id'], ['name' => 'idx_conversations_agent_id'])
              ->addIndex(['status'], ['name' => 'idx_conversations_status'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
              ->create();
    }
}
