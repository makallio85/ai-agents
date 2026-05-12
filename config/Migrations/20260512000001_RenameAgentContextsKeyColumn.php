<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Renames the `key` column in agent_contexts to `context_key`.
 *
 * `key` is a reserved word in MariaDB. When CakePHP builds unquoted queries
 * (INSERT, LIKE, WHERE) against this column it produces SQLSTATE[42000]
 * syntax errors, silently failing saves and returning empty reads.
 *
 * Renaming to `context_key` eliminates the reserved-word clash without
 * requiring any quoting workarounds in application code.
 */
class RenameAgentContextsKeyColumn extends BaseMigration
{
    public function up(): void
    {
        $this->table('agent_contexts')
            ->renameColumn('key', 'context_key')
            ->update();
    }

    public function down(): void
    {
        $this->table('agent_contexts')
            ->renameColumn('context_key', 'key')
            ->update();
    }
}
