<?php

namespace App\Actions\Housekeeping;

use App\Actions\Cms\UpdateRoomStatusAction;
use App\Actions\Operations\UpdateRequestStatusAction;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\RoomStatus;
use App\Enums\ServiceRequestStatus;
use App\Events\HousekeepingTaskChanged;
use App\Exceptions\HousekeepingTaskTransitionException;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of a housekeeping task's status (Phase 6, D-03, D-05,
 * D-07, D-11).
 *
 *  - Lock order (D-05): the room row, then the task row, in one
 *    `DB::transaction(fn, 3)`; UpdateRoomStatusAction's own room lock is a
 *    re-lock inside the same transaction.
 *  - The D-05 transition table; a rejected move throws
 *    `housekeeping_task_transition_invalid` {from, to, allowed} and writes
 *    nothing. Each accepted move writes one history row.
 *  - `in_progress` stamps `started_at` once and self-assigns an unassigned
 *    task; `done` stamps `completed_at` / `completed_by`. The model re-derives
 *    the dedupe key, so a closed task frees the room for a new one.
 *  - Room hook (D-07), turnover `done` only: a dirty room goes to available
 *    through UpdateRoomStatusAction (the only writer of `rooms.status`) with
 *    reason `turnover`, which tells that action not to close the task again;
 *    an available room is left alone; a maintenance room stays, and the
 *    activity log records `room_left_in_maintenance`. Cancelling never touches
 *    the room, and other task types never do.
 *  - Request link (D-11): a request task reaching `done` completes its service
 *    request while that request is still active. A cancelled task leaves the
 *    request alone.
 */
class UpdateHousekeepingTaskStatusAction
{
    /** Room-status reason used by the turnover hook; UpdateRoomStatusAction skips its closer for it. */
    public const ROOM_STATUS_REASON = 'turnover';

    public function __construct(private readonly UpdateRoomStatusAction $updateRoomStatus) {}

    public function handle(HousekeepingTask $task, HousekeepingTaskStatus $to, ?string $reason, ?User $actor): array
    {
        return DB::transaction(function () use ($task, $to, $reason, $actor) {
            $room   = Room::withTrashed()->whereKey($task->room_id)->lockForUpdate()->firstOrFail();
            $locked = HousekeepingTask::whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new HousekeepingTaskTransitionException(
                    __('custom.errors.housekeeping_task_transition_invalid'),
                    [
                        'from'    => $from->value,
                        'to'      => $to->value,
                        'allowed' => array_map(static fn (HousekeepingTaskStatus $s) => $s->value, $from->allowedTargets()),
                    ],
                );
            }

            $locked->status = $to;

            if ($to === HousekeepingTaskStatus::IN_PROGRESS) {
                $locked->started_at ??= now();

                if ($locked->assigned_user_id === null && $actor !== null) {
                    $locked->assigned_user_id = $actor->getKey();
                }
            }

            if ($to === HousekeepingTaskStatus::DONE) {
                $locked->completed_at = now();
                $locked->completed_by = $actor?->getKey();
            }

            $locked->save();

            HousekeepingTaskStatusHistory::create([
                'housekeeping_task_id' => $locked->id,
                'from_status'          => $from->value,
                'to_status'            => $to->value,
                'changed_by'           => $actor?->getKey(),
                'reason'               => $reason,
            ]);

            if ($to === HousekeepingTaskStatus::DONE) {
                $this->afterDone($locked, $room, $actor);
            }

            HousekeepingTaskChanged::dispatch($locked);

            return ['data' => $locked->fresh(['room', 'assignedUser']), 'code' => 200];
        }, 3);
    }

    private function afterDone(HousekeepingTask $task, Room $room, ?User $actor): void
    {
        if ($task->type === HousekeepingTaskType::TURNOVER && ! $room->trashed()) {
            match ($room->status) {
                RoomStatus::DIRTY       => $this->updateRoomStatus->handle($room, RoomStatus::AVAILABLE, self::ROOM_STATUS_REASON, $actor),
                RoomStatus::MAINTENANCE => $this->logLeftInMaintenance($task, $room, $actor),
                default                 => null,
            };
        }

        if ($task->type === HousekeepingTaskType::REQUEST && $task->service_request_id !== null) {
            $request = $task->serviceRequest;

            if ($request && in_array($request->status, ServiceRequestStatus::active(), true)) {
                // Resolved here, not injected: UpdateRequestStatusAction injects
                // this class (D-11 two-way link), so constructor injection both
                // ways would recurse. The request action finds this task already
                // done and does not loop back.
                app(UpdateRequestStatusAction::class)->handle($request, ServiceRequestStatus::COMPLETED->value, $actor, 'housekeeping_task_done');
            }
        }
    }

    private function logLeftInMaintenance(HousekeepingTask $task, Room $room, ?User $actor): void
    {
        $logger = activity()->performedOn($task);

        if ($actor) {
            $logger->causedBy($actor);
        }

        $logger->withProperties(['room_left_in_maintenance' => true, 'room_uuid' => $room->uuid])
            ->log('housekeeping_task.completed');
    }
}
