<?php

namespace App\Http\Resources\Booking;

use App\Base\BaseResource;
use App\Models\Reservation;
use Illuminate\Http\Request;

/**
 * The two-flag entitlement snapshot (ARCHITECTURE §3.7) for the bearer token,
 * plus just enough of the stay for the app to render "You're in room 812".
 *
 * Wraps the array StayService::checkInStatus() builds, not a model — the
 * answer is about the guest, and "no booking at all" is a valid payload.
 */
class CheckInStatusResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        /** @var Reservation|null $reservation */
        $reservation = $this->resource['reservation'];

        return [
            // Unlocks the pre-arrival tier.
            'has_booking'   => $this->resource['has_booking'],
            // Unlocks the in-room tier — same rule the `is_checked_in` middleware enforces.
            'is_checked_in' => $this->resource['is_checked_in'],
            'reservation'   => $reservation ? [
                'uuid'         => $reservation->uuid,
                'booking_code' => $reservation->booking_code,
                'status'       => $reservation->status,
                'check_in'     => $reservation->check_in?->toDateString(),
                'check_out'    => $reservation->check_out?->toDateString(),
                // Null for stays that predate the checked_in_at column, and for
                // a booking the guest has not arrived at yet.
                'checked_in_at'    => $reservation->checked_in_at?->toIso8601String(),
                'nights_remaining' => $reservation->nightsRemaining(),
                // Assigned at check-in, so null until the guest is in the room.
                'room_number'      => $reservation->rooms->first()?->room?->number,
            ] : null,
        ];
    }
}
