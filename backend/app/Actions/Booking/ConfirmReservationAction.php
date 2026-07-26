<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ConfirmReservationAction
{
    public function handle(Reservation $reservation): array
    {
        if (! in_array($reservation->status, [ReservationStatus::PENDING, ReservationStatus::PENDING_VERIFICATION], true)) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        DB::transaction(fn () => $reservation->update(['status' => ReservationStatus::CONFIRMED]));

        return ['data' => $reservation->refresh(), 'code' => 200];
    }
}
