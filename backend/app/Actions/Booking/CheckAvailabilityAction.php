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

    private function occupiedCount(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        return ReservationRoom::where('room_type_id', $roomTypeId)
            ->whereHas('reservation', function ($q) use ($checkIn, $checkOut) {
                // whereDate() strips time component so adjacent stays don't falsely collide
                // (Eloquent 'date' cast stores via fromDateTime() which may include H:i:s)
                $q->whereDate('check_in', '<', $checkOut)
                  ->whereDate('check_out', '>', $checkIn)
                  ->where(function ($q) {
                      $q->whereIn('status', [
                            Reservation::STATUS_PENDING,
                            Reservation::STATUS_CONFIRMED,
                            Reservation::STATUS_CHECKED_IN,
                          ])
                        ->orWhere(function ($q) {
                            $q->where('status', Reservation::STATUS_PENDING_VERIFICATION)
                              ->where('hold_expires_at', '>', now());
                        });
                  });
            })
            ->count();
    }
}
