<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateLabels extends BaseMigration
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
        $table = $this->table('labels');
        $table->addColumn('name', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('slug', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('color', 'string', ['limit' => 7, 'null' => false, 'default' => '#0075ca', 'comment' => 'Hex color for GitHub label'])
              ->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'default' => null])
              ->addColumn('keywords', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON array of keywords for auto-detection'])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['slug'], ['unique' => true, 'name' => 'uq_labels_slug'])
              ->create();
    }
}
