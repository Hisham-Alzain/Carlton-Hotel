<?php

namespace App\Actions\Booking;

use App\Enums\DigitalKeyRevocationReason;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Revoke a stay's digital key (Phase 4, D-11): nulls the code and its hash and
 * records when and why, in one transaction.
 *
 * Revokes any issued, unrevoked key, expired or not, so the expiry sweep can
 * use it. A no-op when there is none. The next approval mints a fresh code.
 */
class RevokeDigitalKeyAction
{
    /**
     * @param  string  $reason  a DigitalKeyRevocationReason value; anything else
     *                          raises ValueError.
     */
    public function handle(Reservation $reservation, string $reason): array
    {
        $reason = DigitalKeyRevocationReason::from($reason)->value;

        return DB::transaction(function () use ($reservation, $reason) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->digital_key_issued_at === null || $locked->digital_key_revoked_at !== null) {
                return ['data' => $locked, 'code' => 200];
            }

            $locked->forceFill([
                'digital_key_code'           => null,
                'digital_key_hash'           => null,
                'digital_key_revoked_at'     => now(),
                'digital_key_revoked_reason' => $reason,
            ])->save();

            return ['data' => $locked, 'code' => 200];
        });
    }
}
