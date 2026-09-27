<?php

namespace Tests\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;

/**
 * SQLite ignores row locks, so this records the lock clause MySQL would
 * compile; blocking behaviour itself is a MySQL-only backstop (D-07).
 *
 * While `$run` executes, the connection's query grammar is swapped for a
 * SQLite grammar whose compileLock() emits MySQL's clause inside an SQL
 * comment (`/* for update *\/`), so SQLite still executes every statement and
 * the listener sees which selects asked for a lock.
 */
trait RecordsRowLocks
{
    /**
     * Run the callback and return every statement compiled with `for update`.
     *
     * @return list<string>
     */
    protected function lockedSelects(callable $run): array
    {
        $connection = DB::connection();
        $recording  = false;
        $statements = [];

        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value)
            {
                if (is_string($value)) {
                    return '/* '.$value.' */';
                }

                return $value ? '/* for update */' : '/* lock in share mode */';
            }
        });

        DB::listen(function ($event) use (&$recording, &$statements): void {
            if ($recording) {
                $statements[] = $event->sql;
            }
        });

        $recording = true;

        try {
            $run();
        } finally {
            $recording = false;
            $connection->useDefaultQueryGrammar();
        }

        return array_values(array_filter($statements, fn (string $sql) => str_contains($sql, 'for update')));
    }

    /** Assert the callback locks at least one row of `$table` with `for update`. */
    protected function assertLocksRow(string $table, callable $run): void
    {
        $locked = $this->lockedSelects($run);

        $this->assertNotEmpty(
            array_filter($locked, fn (string $sql) => str_contains($sql, 'from "'.$table.'"')),
            "Expected a `for update` select on \"{$table}\"; locked statements seen:\n".implode("\n", $locked ?: ['(none)']),
        );
    }
}
