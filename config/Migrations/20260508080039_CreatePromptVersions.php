<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePromptVersions extends BaseMigration
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
        $table = $this->table('prompt_versions');
        $table->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('version', 'integer', ['null' => false, 'default' => 1])
              ->addColumn('name', 'string', ['limit' => 150, 'null' => false])
              ->addColumn('content', 'text', ['null' => false])
              ->addColumn('is_active', 'boolean', ['null' => false, 'default' => false])
              ->addColumn('created_by', 'integer', ['null' => true, 'default' => null])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['agent_id'], ['name' => 'idx_prompt_versions_agent_id'])
              ->addIndex(['agent_id', 'version'], ['unique' => true, 'name' => 'uq_prompt_versions_agent_version'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->create();
    }
}
