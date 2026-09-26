<?php

namespace App\Actions\Booking;

use App\Enums\DigitalKeyRevocationReason;
use App\Models\Reservation;

/**
 * The expiry sweep (Phase 4, D-11): revokes every issued, unrevoked key whose
 * expiry has passed, through the same RevokeDigitalKeyAction every other
 * revocation uses (one path, no mass update). Expired keys are already hidden
 * at read time; this nulls the stored code and hash.
 */
class RevokeExpiredDigitalKeysAction
{
    public function __construct(private readonly RevokeDigitalKeyAction $revokeKey) {}

    public function handle(): int
    {
        $revoked = 0;

        Reservation::query()
            ->whereNotNull('digital_key_issued_at')
            ->whereNull('digital_key_revoked_at')
            ->where('digital_key_expires_at', '<=', now())
            ->chunkById(100, function ($reservations) use (&$revoked) {
                foreach ($reservations as $reservation) {
                    $this->revokeKey->handle($reservation, DigitalKeyRevocationReason::EXPIRED->value);
                    $revoked++;
                }
            });

        return $revoked;
    }
}
