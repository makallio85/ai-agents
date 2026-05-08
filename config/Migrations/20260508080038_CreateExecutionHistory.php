<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateExecutionHistory extends BaseMigration
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
        $table = $this->table('execution_history');
        $table->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
              ->addColumn('execution_id', 'string', ['limit' => 36, 'null' => false, 'comment' => 'UUID'])
              ->addColumn('job_type', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'comment' => 'pending|running|success|failed'])
              ->addColumn('started_at', 'datetime', ['null' => true, 'default' => null])
              ->addColumn('finished_at', 'datetime', ['null' => true, 'default' => null])
              ->addColumn('result', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON'])
              ->addColumn('error_message', 'text', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['agent_id'], ['name' => 'idx_execution_history_agent_id'])
              ->addIndex(['user_id'], ['name' => 'idx_execution_history_user_id'])
              ->addIndex(['execution_id'], ['name' => 'idx_execution_history_execution_id'])
              ->addIndex(['status'], ['name' => 'idx_execution_history_status'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
              ->create();
    }
}
