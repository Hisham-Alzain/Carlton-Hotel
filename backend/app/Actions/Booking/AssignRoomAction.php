<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Events\RoomAssigned;
use App\Exceptions\ReservationStateException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Exceptions\RoomOutOfOrderException;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;

/**
 * Pure room assignment (D-03): gives a reservation a room before arrival
 * (`confirmed`) or moves a guest to another room during the stay
 * (`checked_in`). Writes only `reservation_rooms.room_id`.
 *
 * It never changes the reservation's status or arrival stamp, and never a
 * room's housekeeping status: check-in is its own verb,
 * `CheckInReservationAction` (D-01). The response still carries `status`, so
 * callers see it did not flip.
 *
 * Locks the reservation, then the room type row (the same order and
 * serialization point as check-in and booking), and checks overlap through
 * the one shared predicate on `CheckAvailabilityAction` (D-04).
 * RoomAssigned — the guest's "room ready" push — fires only when a checked-in
 * guest's room actually changed (D-05).
 */
class AssignRoomAction
{
    public function __construct(private readonly CheckAvailabilityAction $availability) {}

    public function handle(Reservation $reservation, ?Room $room = null): array
    {
        [$locked, $moved] = DB::transaction(function () use ($reservation, $room) {
            // Re-read under the lock: a stale model must never decide.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            $allowed = [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN];
            if (! in_array($locked->status, $allowed, true)) {
                throw new ReservationStateException(__('custom.errors.reservation_state'), [
                    'status'  => $locked->status->value,
                    'allowed' => ['confirmed', 'checked_in'],
                ]);
            }

            // Multi-room reservations: the first line is authoritative.
            $line = $locked->rooms()->orderBy('id')->first();
            if (! $line) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            // Lock order is always reservation, then room type.
            RoomType::withTrashed()->whereKey($line->room_type_id)->lockForUpdate()->first();

            // No room given: keep (re-validate) the one already reserved.
            $target = $room ?? $line->room;
            if (! $target) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            // Re-read the target room under a lock: it was resolved (explicit or
            // pre-assigned) outside any lock, so a concurrent PATCH
            // .../status to maintenance could otherwise land between that read
            // and this commit. Lock order stays reservation, room type, room.
            $target = Room::whereKey($target->id)->lockForUpdate()->first();

            if ((int) $target->room_type_id !== (int) $line->room_type_id) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            if ($target->status === RoomStatus::MAINTENANCE) {
                throw new RoomOutOfOrderException(__('custom.errors.room_out_of_order'), [
                    'room_uuid'           => $target->uuid,
                    'housekeeping_status' => $target->status->value,
                ]);
            }

            // A room held by another booking over these dates — even a merely
            // pending one — cannot be handed to this reservation.
            if (! $this->availability->isRoomFree($target, $locked)) {
                throw new RoomAlreadyAssignedException(__('custom.errors.room_already_assigned'));
            }

            $moved = (int) $line->room_id !== (int) $target->id;
            if ($moved) {
                $line->update(['room_id' => $target->id]);
            }

            return [$locked, $moved];
        });

        // After commit, and only for a real move during the stay (D-03, D-05).
        if ($moved && $locked->status === ReservationStatus::CHECKED_IN) {
            event(new RoomAssigned($locked));
        }

        return ['data' => $locked->fresh()->load(['rooms.room', 'rooms.roomType']), 'code' => 200];
    }
}
