<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\HotelClock;
use Illuminate\Support\Facades\DB;

/**
 * Mint the digital key for a stay (Phase 4, D-11).
 *
 * A display credential, NOT lock-grade: it is shown in the guest app and
 * checked by eye. Preconditions for a real lock (non-static OTP, Sanctum token
 * expiry, a hash-based verifier) are not met yet.
 *
 * Idempotent: an active key is kept (200); a fresh random code is minted
 * (201) only when no issued, unrevoked, unexpired key exists, so any issuance
 * after a revocation or expiry yields a new code. Stays that do not hold their
 * room (anything but confirmed / checked_in) get nothing (200, untouched).
 */
class IssueDigitalKeyAction
{
    /** 32 symbols, no 0/O/1/I: 12 characters carry about 60 bits. */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const HOLDING = [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN];

    public function handle(Reservation $reservation): array
    {
        return DB::transaction(function () use ($reservation) {
            // Two approvals racing on one stay serialise here: the second sees
            // the first one's key and keeps it.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, self::HOLDING, true) || $locked->hasActiveDigitalKey()) {
                return ['data' => $locked, 'code' => 200];
            }

            $code = $this->generateCode();

            // forceFill: no digital_key_* column is mass-assignable (D-11).
            $locked->forceFill([
                'digital_key_code'           => $code, // encrypted at rest by the cast
                'digital_key_hash'           => hash_hmac('sha256', $code, (string) config('app.key')),
                'digital_key_issued_at'      => now(),
                'digital_key_expires_at'     => HotelClock::checkOutAt($locked->check_out),
                'digital_key_revoked_at'     => null,
                'digital_key_revoked_reason' => null,
            ])->save();

            return ['data' => $locked, 'code' => 201];
        });
    }

    /**
     * `XXXX-XXXX-XXXX` drawn with the CSPRNG. Never derived from any id, date
     * or contact detail of the stay.
     */
    public function generateCode(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $raw = '';

        for ($i = 0; $i < 12; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return implode('-', str_split($raw, 4));
    }
}
