<?php

namespace App\Actions\Operations;

use App\Actions\Housekeeping\AssignHousekeepingTaskAction;
use App\Actions\Tickets\AssignTicketAction;
use App\Exceptions\ServiceRequestClosedException;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AssigneeEligibility;
use App\Support\ClaimGuard;
use App\Support\OperationsQueueMirror;
use App\Support\OperationsQueueType;
use App\Traits\MirrorsToFirestore;
use Illuminate\Support\Facades\DB;
use LogicException;

class AssignRequestAction
{
    use MirrorsToFirestore;

    public function __construct(
        private readonly AssignHousekeepingTaskAction $assignHousekeepingTask,
        private readonly AssignTicketAction $assignTicket,
    ) {}

    /**
     * `$actor` (Phase 6, FA-6.05-2) is optional for the service-request and
     * task arms (recorded on the task's pending → assigned history row). The
     * ticket arm delegates to AssignTicketAction and requires it (council A2).
     * `$claim` (Phase 7, 07-09, D-22) is passed to every arm: the type's writer
     * runs closed check → ClaimGuard → assign inside its own lock.
     */
    public function handle(ServiceRequest|Ticket|HousekeepingTask $item, User $user, ?User $actor = null, bool $claim = false): array
    {
        // D-13: a task is assigned only by its single writer (transition rules,
        // room → task lock order, history). Its HousekeepingTaskChanged event
        // mirrors it (D-11b), so no mirror call on this path.
        if ($item instanceof HousekeepingTask) {
            return $this->assignHousekeepingTask->handle($item, $user, $actor, $claim);
        }

        if ($item instanceof ServiceRequest) {
            return $this->assignServiceRequest($item, $user, $claim);
        }

        // D-07: a ticket is assigned only by its single writer (closed check,
        // eligibility, timeline); TicketChanged mirrors it after commit (D-21).
        if ($actor === null) {
            throw new LogicException('The ticket arm of AssignRequestAction requires an authenticated actor (Phase 7, council A2).');
        }

        return $this->assignTicket->handle($item, $user, $actor, $claim);
    }

    /**
     * Phase 7 (D-09, council A1, PR-1): locked, closed-checked and
     * eligibility-checked. Lock order: the service-request row is the last
     * lock in every existing chain (room → task → request) and nothing is
     * locked after it, so no cycle. The mirror runs after commit, once.
     */
    private function assignServiceRequest(ServiceRequest $item, User $user, bool $claim): array
    {
        return DB::transaction(function () use ($item, $user, $claim) {
            $locked = ServiceRequest::whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $type   = OperationsQueueType::forModel($locked);

            if (! in_array($locked->status->value, $type->openStatuses(), true)) {
                throw new ServiceRequestClosedException(
                    __('custom.errors.service_request_closed'),
                    ['status' => $locked->status->value],
                );
            }

            // 07-09 (D-22): claim = closed check → ClaimGuard under the SR lock;
            // status stays as it is and eligibility is skipped for the claimer.
            if ($claim) {
                if (ClaimGuard::check($locked, $user)) {
                    return ['data' => $item->refresh(), 'code' => 200, 'claimed' => false];
                }
            } else {
                AssigneeEligibility::assert($user, $type->statusPermission);
            }

            $locked->update(['assigned_user_id' => $user->id]);
            $item->refresh();

            DB::afterCommit(fn () => $this->mirrorToFirestore(
                'ops_queue',
                OperationsQueueMirror::documentId($item),
                OperationsQueueMirror::payload($item),
            ));

            return ['data' => $item, 'code' => 200, 'claimed' => $claim];
        }, 3);
    }
}
