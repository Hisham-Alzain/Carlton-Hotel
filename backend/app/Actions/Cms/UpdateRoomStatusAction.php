<?php

namespace App\Actions\Cms;

use App\Actions\Housekeeping\CloseOpenTurnoverTaskAction;
use App\Actions\Housekeeping\UpdateHousekeepingTaskStatusAction;
use App\Enums\RoomStatus;
use App\Exceptions\RoomStatusTransitionException;
use App\Models\Room;
use App\Models\RoomStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of a room's housekeeping status (Phase 2, D-03..D-05).
 *
 * Row-locks the room, checks the D-04 transition table, then writes the new
 * status, the denormalised `status_changed_at` / `status_changed_by` and one
 * `room_status_history` row in one transaction. A rejected transition throws
 * inside the transaction, so nothing is written. Never reads or writes
 * reservations, availability or room assignment: housekeeping status is
 * independent of bookability.
 *
 * A null actor records a system change (check-out's ensure-dirty, Phase 3
 * D-08): `status_changed_by` and `room_status_history.changed_by` stay null.
 *
 * Phase 6 (D-04): a dirty → available change closes the room's open turnover
 * task through CloseOpenTurnoverTaskAction (room locked before task), unless
 * the caller is the task path itself (reason `turnover`), which has already
 * closed its task.
 */
class UpdateRoomStatusAction
{
    public function __construct(private readonly CloseOpenTurnoverTaskAction $closeTurnover) {}

    public function handle(Room $room, RoomStatus $to, ?string $reason, ?User $actor): array
    {
        return DB::transaction(function () use ($room, $to, $reason, $actor) {
            $locked = Room::whereKey($room->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new RoomStatusTransitionException(
                    __('custom.errors.room_status_transition_invalid'),
                    [
                        'from'    => $from->value,
                        'to'      => $to->value,
                        'allowed' => array_map(static fn (RoomStatus $s) => $s->value, $from->allowedTargets()),
                    ],
                );
            }

            $locked->forceFill([
                'status'            => $to,
                'status_changed_at' => now(),
                'status_changed_by' => $actor?->getKey(),
            ])->save();

            RoomStatusHistory::create([
                'room_id'     => $locked->id,
                'from_status' => $from->value,
                'to_status'   => $to->value,
                'changed_by'  => $actor?->getKey(),
                'reason'      => $reason,
            ]);

            // Phase 6 (D-04): the room board's dirty → available closes the
            // room's open turnover task, still under this room lock. The task
            // path passes reason `turnover` and has already closed its task.
            if ($from === RoomStatus::DIRTY
                && $to === RoomStatus::AVAILABLE
                && $reason !== UpdateHousekeepingTaskStatusAction::ROOM_STATUS_REASON) {
                $this->closeTurnover->handle($locked, $actor);
            }

            return ['data' => $locked->fresh(), 'code' => 200];
        });
    }
}
