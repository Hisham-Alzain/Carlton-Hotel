<?php

namespace App\Actions\Operations;

use App\Actions\Housekeeping\AssignHousekeepingTaskAction;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;

class AssignRequestAction
{
    use MirrorsToFirestore;

    public function __construct(private readonly AssignHousekeepingTaskAction $assignHousekeepingTask) {}

    /**
     * `$actor` (Phase 6, FA-6.05-2) is optional and only used by the task arm,
     * where it is recorded on the pending → assigned history row.
     */
    public function handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user, ?User $actor = null): array
    {
        // D-13: a task is assigned only by its single writer (transition rules,
        // room → task lock order, history). Its HousekeepingTaskChanged event
        // mirrors it (D-11b), so no mirror call on this path.
        if ($item instanceof HousekeepingTask) {
            return $this->assignHousekeepingTask->handle($item, $user, $actor);
        }

        $item->update(['assigned_user_id' => $user->id]);
        $item->refresh();

        $this->mirrorToFirestore('ops_queue', OperationsQueueMirror::documentId($item), OperationsQueueMirror::payload($item));

        return ['data' => $item, 'code' => 200];
    }
}
