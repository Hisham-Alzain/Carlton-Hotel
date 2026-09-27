<?php

namespace App\Console\Commands;

use App\Actions\Housekeeping\ReconcileHousekeepingTasksAction;
use Illuminate\Console\Command;

/**
 * Run by hand (not scheduled): after a failed turnover listener, or when the
 * board and the room statuses look out of step (Phase 6, D-02).
 */
class ReconcileHousekeepingTasks extends Command
{
    protected $signature   = 'housekeeping:reconcile';
    protected $description = 'Re-derive stale housekeeping dedupe keys and list dirty rooms without an open turnover task';

    public function handle(ReconcileHousekeepingTasksAction $action): int
    {
        $result = $action->handle()['data'];
        $rooms  = $result['dirty_rooms_without_turnover'];

        $this->info("Cleared {$result['cleared']} stale dedupe key(s).");
        $this->info("Restored {$result['restored']} missing dedupe key(s).");

        if ($result['duplicate_open_tasks'] !== []) {
            $this->warn('Duplicate open tasks (close by hand): '.implode(', ', $result['duplicate_open_tasks']));
        }
        $this->info('Dirty rooms without an open turnover task: '.($rooms === [] ? 'none' : implode(', ', $rooms)));

        return Command::SUCCESS;
    }
}
