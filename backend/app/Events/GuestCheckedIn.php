<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by the check-in verb (CheckInReservationAction) after its
 * transaction commits. Shares SendRoomReadyNotification with RoomAssigned, so
 * the guest's "room ready" push fires at check-in (D-05).
 */
class GuestCheckedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Reservation $reservation) {}
}
