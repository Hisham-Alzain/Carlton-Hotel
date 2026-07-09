<?php

namespace App\Actions\Booking;

use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ConfirmReservationAction
{
    public function handle(Reservation $reservation): array
    {
        if (! in_array($reservation->status, [Reservation::STATUS_PENDING, Reservation::STATUS_PENDING_VERIFICATION])) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        DB::transaction(fn () => $reservation->update(['status' => Reservation::STATUS_CONFIRMED]));

        return ['data' => $reservation->refresh(), 'code' => 200];
    }
}
