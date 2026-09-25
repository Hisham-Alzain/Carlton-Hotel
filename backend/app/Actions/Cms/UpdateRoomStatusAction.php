<?php

namespace App\Actions\Cms;

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
 */
class UpdateRoomStatusAction
{
    public function handle(Room $room, RoomStatus $to, ?string $reason, User $actor): array
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
                'status_changed_by' => $actor->getKey(),
            ])->save();

            RoomStatusHistory::create([
                'room_id'     => $locked->id,
                'from_status' => $from->value,
                'to_status'   => $to->value,
                'changed_by'  => $actor->getKey(),
                'reason'      => $reason,
            ]);

            return ['data' => $locked->fresh(), 'code' => 200];
        });
    }
}
