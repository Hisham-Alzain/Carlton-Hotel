<?php

namespace App\Actions\Service;

use App\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Do-not-disturb on the guest's active stay.
 *
 * Stored as an expiry rather than a boolean so a toggle the guest forgets about
 * clears itself overnight instead of blocking housekeeping indefinitely.
 * Deliberately not a service_requests row: DND is state, not work, and a
 * never-closing "DND" ticket would sit in the operations queue forever.
 */
class SetDndAction
{
    public function handle(Reservation $reservation, bool $enabled, ?string $until = null): array
    {
        $expiry = null;

        if ($enabled) {
            // Default to the end of the current hotel day.
            $expiry = $until !== null ? Carbon::parse($until) : now()->endOfDay();
        }

        DB::transaction(fn () => $reservation->update(['dnd_until' => $expiry]));

        $reservation->refresh();

        return [
            'data' => [
                'enabled' => $reservation->isDndActive(),
                'until'   => $reservation->dnd_until?->toIso8601String(),
            ],
            'code' => 200,
        ];
    }
}
