<?php

namespace App\Actions\Booking;

use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    private const CANCELLABLE = [
        Reservation::STATUS_PENDING_VERIFICATION,
        Reservation::STATUS_PENDING,
        Reservation::STATUS_CONFIRMED,
    ];

    public function handle(Reservation $reservation): array
    {
        if (! in_array($reservation->status, self::CANCELLABLE)) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        DB::transaction(fn () => $reservation->update(['status' => Reservation::STATUS_CANCELLED]));

        return ['data' => null, 'code' => 204];
    }
}
