<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Phase 9 query budgets (D-22, R-4). Counts the statements a callable runs,
 * ignoring `activity_log` inserts (audit bookkeeping, not domain work). Wrap
 * only the action/service call so auth/permission/cache bookkeeping is not
 * counted; the house `expectsDatabaseQueryCount()` cannot exclude activity rows.
 */
trait CountsDomainQueries
{
    /**
     * Run the callback and return the domain SQL statements it executed.
     *
     * @return list<string>
     */
    protected function domainQueries(callable $run): array
    {
        $recording  = false;
        $statements = [];

        DB::listen(function ($event) use (&$recording, &$statements): void {
            if ($recording && ! str_contains($event->sql, 'activity_log')) {
                $statements[] = $event->sql;
            }
        });

        $recording = true;

        try {
            $run();
        } finally {
            $recording = false;
        }

        return $statements;
    }

    protected function countDomainQueries(callable $run): int
    {
        return count($this->domainQueries($run));
    }
}
