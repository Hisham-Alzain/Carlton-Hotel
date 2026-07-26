<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredHoldsAction
{
    public function handle(): int
    {
        return DB::transaction(fn () =>
            Reservation::where('status', ReservationStatus::PENDING_VERIFICATION)
                ->where('hold_expires_at', '<', now())
                ->update(['status' => ReservationStatus::CANCELLED])
        );
    }
}
