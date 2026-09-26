<?php

namespace App\Actions\Booking;

use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Sets, replaces or clears a reservation's staff notes (D-11), in any status.
 *
 * A whole-value overwrite of one column: no row lock, the last committed
 * writer wins and two writers never merge. The edit history is the model's
 * LogsActivity trail (old/new text, staff causer); an unchanged value writes
 * no activity row.
 */
class UpdateReservationNotesAction
{
    public function handle(Reservation $reservation, ?string $notes): array
    {
        DB::transaction(function () use ($reservation, $notes) {
            $reservation->update(['notes' => $notes]);
        });

        return ['data' => $reservation->fresh()->load(['rooms.room', 'rooms.roomType', 'guest']), 'code' => 200];
    }
}
