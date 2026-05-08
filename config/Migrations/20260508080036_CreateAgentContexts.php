<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAgentContexts extends BaseMigration
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
        $table = $this->table('agent_contexts');
        $table->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('key', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('value', 'text', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['agent_id'], ['name' => 'idx_agent_contexts_agent_id'])
              ->addIndex(['agent_id', 'key'], ['unique' => true, 'name' => 'uq_agent_contexts_agent_key'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
