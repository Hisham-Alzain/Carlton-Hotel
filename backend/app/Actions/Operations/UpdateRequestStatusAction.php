<?php

namespace App\Actions\Operations;

use App\Actions\Housekeeping\UpdateHousekeepingTaskStatusAction;
use App\Actions\Tickets\UpdateTicketStatusAction;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketStatus;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;
use Illuminate\Support\Facades\DB;
use LogicException;

class UpdateRequestStatusAction
{
    use MirrorsToFirestore;

    public const TASK_CANCEL_REASON = 'service_request_closed';

    public function __construct(
        private readonly UpdateHousekeepingTaskStatusAction $updateTaskStatus,
        private readonly UpdateTicketStatusAction $updateTicketStatus,
    ) {}

    /**
     * `$actor` and `$reason` were added in Phase 6 (D-13). Tasks and (Phase 7,
     * D-07) tickets go through their single writers, which carry the reason
     * into their history/timeline. A null actor stays valid for the
     * service-request and housekeeping arms only; the ticket arm throws a
     * LogicException without one (council A2 — every HTTP caller has one).
     */
    public function handle(ServiceRequest|Ticket|HousekeepingTask $item, string $status, ?User $actor = null, ?string $reason = null): array
    {
        // D-13: a task changes status only through its single writer (transition
        // table, room hook, history; mirrored via HousekeepingTaskChanged).
        if ($item instanceof HousekeepingTask) {
            return $this->updateTaskStatus->handle($item, HousekeepingTaskStatus::from($status), $reason, $actor);
        }

        // D-07: a ticket changes status only through its single writer, so the
        // queue and /support-tickets/{ticket}/status share one transition table
        // and one timeline; TicketChanged mirrors it after commit (D-21).
        if ($item instanceof Ticket) {
            if ($actor === null) {
                throw new LogicException('The ticket arm of UpdateRequestStatusAction requires an authenticated actor (Phase 7, council A2).');
            }

            return $this->updateTicketStatus->handle($item, TicketStatus::from($status), $reason, $actor);
        }

        return $this->updateServiceRequest($item, $status, $actor);
    }

    /**
     * The request's status write and the D-11 cancel of its open request task
     * commit or roll back together (QA minor 1): a failing cancel leaves the
     * request untouched instead of closed with an open task.
     *
     * Lock order matches the task path (D-05): room(s) → task(s) → request, so
     * this path and UpdateHousekeepingTaskStatusAction (room → task → request
     * via its D-11 completion) cannot deadlock each other. The cancel runs
     * through the task's single writer, which re-locks the same room and task
     * inside this transaction. The Firestore mirror is deferred to commit so
     * it never publishes a status that was rolled back.
     *
     * When the task path completes the request, this runs nested inside the
     * task's transaction; that task is already done, so no open task is found
     * and nothing loops.
     */
    private function updateServiceRequest(ServiceRequest $item, string $status, ?User $actor): array
    {
        return DB::transaction(function () use ($item, $status, $actor) {
            $closes = in_array($status, [ServiceRequestStatus::COMPLETED->value, ServiceRequestStatus::CANCELLED->value], true);
            $tasks  = collect();

            if ($closes) {
                $roomIds = HousekeepingTask::open()
                    ->where('service_request_id', $item->getKey())
                    ->pluck('room_id')->unique()->sort()->values();

                if ($roomIds->isNotEmpty()) {
                    Room::withTrashed()->whereKey($roomIds->all())->orderBy('id')->lockForUpdate()->get();
                }

                $tasks = HousekeepingTask::open()
                    ->where('service_request_id', $item->getKey())
                    ->orderBy('id')->lockForUpdate()->get();
            }

            $locked = ServiceRequest::whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $locked->update(['status' => $status]);

            $tasks->each(fn (HousekeepingTask $task) => $this->updateTaskStatus->handle(
                $task,
                HousekeepingTaskStatus::CANCELLED,
                self::TASK_CANCEL_REASON,
                $actor,
            ));

            $item->refresh();
            DB::afterCommit(fn () => $this->mirror($item));

            return ['data' => $item, 'code' => 200];
        }, 3);
    }

    private function mirror(ServiceRequest $item): void
    {
        $this->mirrorToFirestore('ops_queue', OperationsQueueMirror::documentId($item), OperationsQueueMirror::payload($item));
    }
}
