<?php

namespace App\Actions\Booking;

use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredHoldsAction
{
    public function handle(): int
    {
        return DB::transaction(fn () =>
            Reservation::where('status', Reservation::STATUS_PENDING_VERIFICATION)
                ->where('hold_expires_at', '<', now())
                ->update(['status' => Reservation::STATUS_CANCELLED])
        );
    }
}
