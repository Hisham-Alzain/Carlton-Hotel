<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    public function handle(Reservation $reservation): array
    {
        if (! $reservation->status->isCancellable()) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        DB::transaction(fn () => $reservation->update(['status' => ReservationStatus::CANCELLED]));

        return ['data' => null, 'code' => 204];
    }
}
