<?php

namespace App\Actions\Booking;

use App\Exceptions\ReservationStateException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class AssignRoomAction
{
    public function handle(Reservation $reservation, Room $room): array
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        $reservationRoom = $reservation->rooms()->first();
        if (! $reservationRoom) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        if ($reservationRoom->room_type_id !== $room->room_type_id) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        // Verify room is not already assigned to another active reservation on these dates
        $alreadyAssigned = $room->reservationRooms()
            ->whereNotNull('room_id')
            ->whereHas('reservation', function ($q) use ($reservation) {
                $q->where('id', '!=', $reservation->id)
                  ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
                  ->where('check_in', '<', $reservation->check_out)
                  ->where('check_out', '>', $reservation->check_in);
            })
            ->exists();

        if ($alreadyAssigned) {
            throw new RoomAlreadyAssignedException(__('custom.errors.room_already_assigned'));
        }

        DB::transaction(function () use ($reservationRoom, $room, $reservation) {
            $reservationRoom->update(['room_id' => $room->id]);
            $reservation->update(['status' => Reservation::STATUS_CHECKED_IN]);
        });

        return ['data' => $reservation->refresh()->load(['rooms.room', 'rooms.roomType']), 'code' => 200];
    }
}
