<?php

namespace App\Actions\Booking;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;

class CheckAvailabilityAction
{
    // Single source of truth for availability — called by both public endpoints
    // and CreateReservationAction (inside the transaction lock).
    public function handle(int $roomTypeId, string $checkIn, string $checkOut): bool
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->count();

        if ($totalRooms === 0) return false;

        $occupied = $this->occupiedCount($roomTypeId, $checkIn, $checkOut);

        return $occupied < $totalRooms;
    }

    public function availableCount(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)->where('is_active', true)->count();
        $occupied   = $this->occupiedCount($roomTypeId, $checkIn, $checkOut);

        return max(0, $totalRooms - $occupied);
    }

    /**
     * The lowest-numbered room of this type with no overlapping booking.
     *
     * Guests are given a specific room at booking time, not at check-in, so the
     * booking confirmation can show "Room 801". Callers must already hold the
     * room_type lock — CreateReservationAction does.
     *
     * Filters on `is_active` only, exactly like the availability count above:
     * `rooms.status` is a housekeeping state for today and says nothing about
     * whether a room is free three weeks out. If the two predicates diverged,
     * availability could report a free room that assignment then refuses.
     */
    public function findFreeRoom(int $roomTypeId, string $checkIn, string $checkOut): ?Room
    {
        // Capacity first. Not every overlapping booking names a room — rows
        // created before rooms were reserved up front, and holds placed through
        // other paths, have a null room_id but still consume inventory. Counting
        // them keeps the old oversell guarantee intact; the room-id filter below
        // only decides *which* free room to hand out.
        if (! $this->handle($roomTypeId, $checkIn, $checkOut)) {
            return null;
        }

        return Room::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->whereNotIn('id', $this->occupiedRoomIds($roomTypeId, $checkIn, $checkOut))
            ->orderBy('number')
            ->lockForUpdate()
            ->first();
    }

    private function occupiedCount(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        return $this->overlapping($roomTypeId, $checkIn, $checkOut)->count();
    }

    /** @return array<int> */
    private function occupiedRoomIds(int $roomTypeId, string $checkIn, string $checkOut): array
    {
        return $this->overlapping($roomTypeId, $checkIn, $checkOut)
            ->whereNotNull('room_id')
            ->pluck('room_id')
            ->all();
    }

    private function overlapping(int $roomTypeId, string $checkIn, string $checkOut)
    {
        return ReservationRoom::where('room_type_id', $roomTypeId)
            ->whereHas('reservation', function ($q) use ($checkIn, $checkOut) {
                // whereDate() strips time component so adjacent stays don't falsely collide
                // (Eloquent 'date' cast stores via fromDateTime() which may include H:i:s)
                $q->whereDate('check_in', '<', $checkOut)
                  ->whereDate('check_out', '>', $checkIn)
                  ->holdingInventory();
            });
    }
}
