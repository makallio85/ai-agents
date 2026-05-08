<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAgents extends BaseMigration
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
        $table = $this->table('agents');
        $table->addColumn('name', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('slug', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('plugin', 'string', ['limit' => 150, 'null' => false, 'comment' => 'CakePHP plugin name'])
              ->addColumn('description', 'text', ['null' => true, 'default' => null])
              ->addColumn('is_enabled', 'boolean', ['null' => false, 'default' => true])
              ->addColumn('llm_provider', 'string', ['limit' => 100, 'null' => true, 'default' => null])
              ->addColumn('llm_model', 'string', ['limit' => 100, 'null' => true, 'default' => null])
              ->addColumn('instructions', 'text', ['null' => true, 'default' => null])
              ->addColumn('config', 'text', ['null' => true, 'default' => null, 'comment' => 'Encrypted JSON'])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['slug'], ['unique' => true, 'name' => 'uq_agents_slug'])
              ->addIndex(['plugin'], ['name' => 'idx_agents_plugin'])
              ->create();
    }
}
