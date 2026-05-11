<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Creates the sessions table for CakePHP database-backed sessions.
 *
 * File-based sessions are lost on every container restart/deploy because
 * they live inside the ephemeral container filesystem. Moving to database
 * sessions keeps users logged in across deployments since MariaDB data
 * persists on a Docker volume.
 *
 * Schema matches config/schema/sessions.sql shipped with CakePHP.
 */
class CreateSessions extends BaseMigration
{
    public function up(): void
    {
        $this->table('sessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'char', ['limit' => 40, 'null' => false, 'encoding' => 'ascii', 'collation' => 'ascii_bin'])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addColumn('data', 'blob', ['null' => true, 'default' => null])
            ->addColumn('expires', 'integer', ['null' => true, 'default' => null, 'signed' => false])
            ->create();
    }

    public function down(): void
    {
        $this->table('sessions')->drop()->save();
    }
}
