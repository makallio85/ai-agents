<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAgentLogs extends BaseMigration
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
        $table = $this->table('agent_logs');
        $table->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('execution_id', 'string', ['limit' => 36, 'null' => false, 'comment' => 'UUID'])
              ->addColumn('correlation_id', 'string', ['limit' => 36, 'null' => true, 'default' => null, 'comment' => 'UUID for tracing'])
              ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
              ->addColumn('level', 'string', ['limit' => 20, 'null' => false, 'comment' => 'info|warning|error|debug'])
              ->addColumn('message', 'text', ['null' => false])
              ->addColumn('context', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON'])
              ->addColumn('duration_ms', 'integer', ['null' => true, 'default' => null])
              ->addColumn('result_state', 'string', ['limit' => 30, 'null' => true, 'default' => null, 'comment' => 'success|failed|pending'])
              ->addColumn('error_message', 'text', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['agent_id'], ['name' => 'idx_agent_logs_agent_id'])
              ->addIndex(['execution_id'], ['name' => 'idx_agent_logs_execution_id'])
              ->addIndex(['correlation_id'], ['name' => 'idx_agent_logs_correlation_id'])
              ->addIndex(['user_id'], ['name' => 'idx_agent_logs_user_id'])
              ->addIndex(['level'], ['name' => 'idx_agent_logs_level'])
              ->addIndex(['created'], ['name' => 'idx_agent_logs_created'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
              ->create();
    }
}
