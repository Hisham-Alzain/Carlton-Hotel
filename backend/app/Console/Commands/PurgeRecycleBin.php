<?php

namespace App\Console\Commands;

use App\Support\RecycleBin;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Empty the recycle bin of everything binned longer than the retention window.
 *
 * ## Why this exists
 *
 * A soft-deleted CMS record kept its row, its `media` rows and every stored file
 * indefinitely — `PurgesMedia` fires on `forceDeleted`, and nothing but a manual
 * `DELETE …/{uuid}/force` ever force-deleted anything. Storage grew forever and
 * "recoverable" silently meant "permanent". This is the scheduled other half.
 *
 * ## Through Eloquent, one row at a time
 *
 * Emphatically not `delete from … where deleted_at < ?`. A mass delete fires no
 * model events, and the events are the entire cleanup mechanism: `PurgesMedia`
 * hooks `forceDeleted` to drop the `media` rows, `Media`'s own delete hook
 * queues the file unlink, and `CascadesSoftDeletes` hooks `deleting` to take the
 * descendants through Eloquent so each of them purges its own media too. Bulk
 * SQL would clear the tables and leave every photograph on the disk with nothing
 * pointing at it — exactly the orphaning the traits were written to stop, at the
 * scale of the whole bin.
 *
 * `lazyById()` keeps memory flat and is safe to delete through: it pages forward
 * by primary key, so rows already visited cannot move the cursor. `reorder()`
 * first, for the same reason `CascadesSoftDeletes` does it — an ordered query
 * breaks `lazyById()`'s forward-by-key invariant and would skip rows.
 *
 * ## Order
 *
 * Deepest cascade level first (`RecycleBin::modelsWithBin()`). A dish and the
 * venue above it carry the same `deleted_at`, so both leave the window together;
 * purging the venue first would take the dish with it and the dish's own pass
 * would report a smaller number than the dry run promised. Upward, every
 * eligible row is destroyed by the pass that counted it.
 */
class PurgeRecycleBin extends Command
{
    protected $signature = 'cms:purge-bin
        {--days= : Retention window in days, overriding cms.recycle_bin.retention_days}
        {--chunk= : Rows held in memory per pass, overriding cms.recycle_bin.chunk}
        {--dry-run : Report what would be purged and destroy nothing}';

    protected $description = 'Permanently delete CMS records that have been in the recycle bin longer than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('cms.recycle_bin.retention_days'));

        if ($days < 1) {
            $this->error('--days must be a positive integer.');

            return Command::INVALID;
        }

        $chunk  = max(1, (int) ($this->option('chunk') ?? config('cms.recycle_bin.chunk')));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = CarbonImmutable::now()->subDays($days);

        $purged = [];
        $total  = 0;

        foreach (RecycleBin::modelsWithBin() as $class) {
            $count = $dryRun
                ? $this->countExpired($class, $cutoff)
                : $this->purgeExpired($class, $cutoff, $chunk);

            if ($count > 0) {
                $purged[RecycleBin::typeToken(new $class)] = $count;
                $total += $count;
            }
        }

        $this->report($purged, $total, $days, $cutoff, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function countExpired(string $class, CarbonImmutable $cutoff): int
    {
        return $this->expired($class, $cutoff)->count();
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function purgeExpired(string $class, CarbonImmutable $cutoff, int $chunk): int
    {
        $purged = 0;

        $this->expired($class, $cutoff)
            ->reorder()
            ->lazyById($chunk)
            ->each(function (Model $row) use (&$purged): void {
                $row->forceDelete();
                $purged++;
            });

        return $purged;
    }

    /**
     * Rows this model has held in the bin since before the cutoff.
     *
     * `onlyTrashed()`, so a live row can never be reached however the window is
     * configured, and `<` on `deleted_at` rather than `<=` so a record binned
     * exactly `days` ago survives its own last day.
     *
     * @param  class-string<Model>  $class
     */
    private function expired(string $class, CarbonImmutable $cutoff): \Illuminate\Database\Eloquent\Builder
    {
        return $class::query()->onlyTrashed()->where('deleted_at', '<', $cutoff);
    }

    /**
     * Say what went, to the operator and to the log.
     *
     * The log line is the point: a scheduled job that destroys content without
     * leaving a record makes "where did that page go?" unanswerable. Counts are
     * of bin entries this run force-deleted; cascade descendants that a parent
     * took with it are covered by their own pass, which runs first.
     *
     * @param  array<string, int>  $purged
     */
    private function report(array $purged, int $total, int $days, CarbonImmutable $cutoff, bool $dryRun): void
    {
        $context = [
            'dry_run'        => $dryRun,
            'retention_days' => $days,
            'cutoff'         => $cutoff->toIso8601String(),
            'total'          => $total,
            'by_type'        => $purged,
        ];

        Log::info($dryRun ? 'Recycle bin purge (dry run)' : 'Recycle bin purged', $context);

        $verb = $dryRun ? 'Would purge' : 'Purged';

        if ($total === 0) {
            $this->info("Nothing in the recycle bin is older than {$days} day(s).");

            return;
        }

        foreach ($purged as $type => $count) {
            $this->line("  {$type}: {$count}");
        }

        $this->info("{$verb} {$total} record(s) binned before {$cutoff->toDateTimeString()} ({$days}-day window).");

        if ($dryRun) {
            $this->comment('Dry run — nothing was destroyed. Re-run without --dry-run to purge.');
        }
    }
}
