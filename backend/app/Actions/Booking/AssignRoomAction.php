<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Events\RoomAssigned;
use App\Exceptions\ReservationStateException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * Checks a guest in, optionally moving them to a different room.
 *
 * Rooms are now picked at booking time, so this is primarily the check-in
 * transition. Passing a `$room` overrides the reserved one (a room move);
 * passing null checks the guest into the room they were already given.
 */
class AssignRoomAction
{
    public function handle(Reservation $reservation, ?Room $room = null): array
    {
        if ($reservation->status !== ReservationStatus::CONFIRMED) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        $reservationRoom = $reservation->rooms()->first();
        if (! $reservationRoom) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        // No override: check in to the room reserved at booking time.
        $room ??= $reservationRoom->room;

        if (! $room) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        if ($reservationRoom->room_type_id !== $room->room_type_id) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        // Verify the room is not held by another booking over these dates. Uses
        // the same predicate as availability, so a room reserved by a merely
        // pending booking cannot be handed to someone else.
        $alreadyAssigned = $room->reservationRooms()
            ->whereNotNull('room_id')
            ->whereHas('reservation', function ($q) use ($reservation) {
                $q->where('id', '!=', $reservation->id)
                  ->where('check_in', '<', $reservation->check_out)
                  ->where('check_out', '>', $reservation->check_in)
                  ->holdingInventory();
            })
            ->exists();

        if ($alreadyAssigned) {
            throw new RoomAlreadyAssignedException(__('custom.errors.room_already_assigned'));
        }

        DB::transaction(function () use ($reservationRoom, $room, $reservation) {
            $reservationRoom->update(['room_id' => $room->id]);
            $reservation->update([
                'status' => ReservationStatus::CHECKED_IN,
                // Mobile's "check-in time" — check_in is only a DATE. Keep the
                // first stamp on re-assignment so the guest's arrival time is
                // not rewritten by a room move.
                'checked_in_at' => $reservation->checked_in_at ?? now(),
            ]);
        });

        // Fulfilled in P9: pushes the "room ready" notification to the guest.
        event(new RoomAssigned($reservation));

        return ['data' => $reservation->refresh()->load(['rooms.room', 'rooms.roomType']), 'code' => 200];
    }
}
