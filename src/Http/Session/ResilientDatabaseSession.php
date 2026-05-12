<?php
declare(strict_types=1);

namespace App\Http\Session;

use Cake\Database\Exception\QueryException;
use Cake\Http\Session\DatabaseSession;

/**
 * DatabaseSession handler that survives concurrent-write conflicts.
 *
 * When the frontend fires several simultaneous API calls (e.g. on page load),
 * all requests share the same session ID and race to UPDATE the sessions row
 * at request end. MariaDB InnoDB raises error 1020 ("Record has changed since
 * last read") when a second writer finds the row was already modified.
 *
 * Error 1020 is safe to ignore: the first writer already committed a valid
 * session. Treating it as success keeps the session alive without any actual
 * data loss.
 */
class ResilientDatabaseSession extends DatabaseSession
{
    public function write(string $id, string $data): bool
    {
        try {
            return parent::write($id, $data);
        } catch (QueryException $e) {
            if ($this->isConcurrentWriteConflict($e)) {
                return true;
            }
            throw $e;
        }
    }

    private function isConcurrentWriteConflict(QueryException $e): bool
    {
        $msg = $e->getMessage();
        // MariaDB / InnoDB error 1020: Record has changed since last read
        return str_contains($msg, '1020') ||
               str_contains($msg, 'Record has changed since last read');
    }
}
