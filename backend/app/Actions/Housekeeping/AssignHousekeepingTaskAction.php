<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Events\HousekeepingTaskChanged;
use App\Exceptions\HousekeepingTaskClosedException;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of a housekeeping task's assignee (Phase 6, D-05).
 *
 * Room row, then task row (D-05 lock order). A pending task becomes assigned
 * (history row `pending → assigned`, reason `assigned`); an assigned or
 * in-progress task only swaps the assignee — the model's activity log records
 * it, no history row (D-03). A done or cancelled task is refused with
 * `housekeeping_task_closed`.
 */
class AssignHousekeepingTaskAction
{
    public function handle(HousekeepingTask $task, User $assignee, ?User $actor = null): array
    {
        return DB::transaction(function () use ($task, $assignee, $actor) {
            Room::withTrashed()->whereKey($task->room_id)->lockForUpdate()->firstOrFail();
            $locked = HousekeepingTask::whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! $from->isOpen()) {
                throw new HousekeepingTaskClosedException(
                    __('custom.errors.housekeeping_task_closed'),
                    ['status' => $from->value],
                );
            }

            $locked->assigned_user_id = $assignee->getKey();

            if ($from === HousekeepingTaskStatus::PENDING) {
                $locked->status = HousekeepingTaskStatus::ASSIGNED;
            }

            $locked->save();

            if ($from === HousekeepingTaskStatus::PENDING) {
                HousekeepingTaskStatusHistory::create([
                    'housekeeping_task_id' => $locked->id,
                    'from_status'          => $from->value,
                    'to_status'            => HousekeepingTaskStatus::ASSIGNED->value,
                    'changed_by'           => $actor?->getKey(),
                    'reason'               => 'assigned',
                ]);
            }

            HousekeepingTaskChanged::dispatch($locked);

            return ['data' => $locked->fresh(['room', 'assignedUser']), 'code' => 200];
        }, 3);
    }
}
