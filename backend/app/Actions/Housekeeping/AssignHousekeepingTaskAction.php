<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Events\HousekeepingTaskChanged;
use App\Exceptions\HousekeepingTaskClosedException;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\User;
use App\Support\AssigneeEligibility;
use App\Support\ClaimGuard;
use App\Support\OperationsQueueType;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of a housekeeping task's assignee (Phase 6, D-05).
 *
 * Room row, then task row (D-05 lock order). A pending task becomes assigned
 * (history row `pending → assigned`, reason `assigned`); an assigned or
 * in-progress task only swaps the assignee — the model's activity log records
 * it, no history row (D-03). A done or cancelled task is refused with
 * `housekeeping_task_closed`. Then (Phase 7, D-09) the assignee must pass
 * AssigneeEligibility with the registry's work permission (housekeeping.update),
 * else `assignee_not_eligible` — the closed check runs first.
 *
 * With `$claim` (Phase 7, 07-09, D-22) the assignee is the claimer: after the
 * closed check ClaimGuard decides no-op / 409 / assign, eligibility is
 * skipped, and a pending task's history row carries reason `claimed`.
 */
class AssignHousekeepingTaskAction
{
    public function handle(HousekeepingTask $task, User $assignee, ?User $actor = null, bool $claim = false): array
    {
        return DB::transaction(function () use ($task, $assignee, $actor, $claim) {
            Room::withTrashed()->whereKey($task->room_id)->lockForUpdate()->firstOrFail();
            $locked = HousekeepingTask::whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! $from->isOpen()) {
                throw new HousekeepingTaskClosedException(
                    __('custom.errors.housekeeping_task_closed'),
                    ['status' => $from->value],
                );
            }

            // 07-09 (D-22): claim = closed check → ClaimGuard under the room →
            // task locks; the claimer passed the work permission, so no eligibility.
            if ($claim) {
                if (ClaimGuard::check($locked, $assignee)) {
                    return ['data' => $locked->fresh(['room', 'assignedUser']), 'code' => 200, 'claimed' => false];
                }
            } else {
                AssigneeEligibility::assert($assignee, OperationsQueueType::forModel($locked)->statusPermission);
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
                    'reason'               => $claim ? 'claimed' : 'assigned',
                ]);
            }

            HousekeepingTaskChanged::dispatch($locked);

            return ['data' => $locked->fresh(['room', 'assignedUser']), 'code' => 200, 'claimed' => $claim];
        }, 3);
    }
}
