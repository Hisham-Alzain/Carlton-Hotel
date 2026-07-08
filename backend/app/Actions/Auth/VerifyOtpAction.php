<?php
namespace App\Actions\Auth;

use App\Exceptions\BookingLinkUnavailableException;
use App\Exceptions\OtpExpiredException;
use App\Exceptions\OtpInvalidException;
use App\Exceptions\OtpLockedException;
use App\Models\Guest;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyOtpAction
{
    private const MAX_ATTEMPTS = 5;

    public function handle(string $identifier, string $code, string $purpose): array
    {
        $otp = OtpCode::forIdentifier($identifier, $purpose)
            ->orderByDesc('id')
            ->first();

        if (! $otp) throw new OtpInvalidException(__('custom.errors.otp_invalid'));
        if ($otp->isConsumed()) throw new OtpInvalidException(__('custom.errors.otp_invalid'));
        if ($otp->isExpired()) throw new OtpExpiredException(__('custom.errors.otp_expired'));
        if ($otp->isLocked(self::MAX_ATTEMPTS)) throw new OtpLockedException(__('custom.errors.otp_locked'));

        if (! Hash::check($code, $otp->code_hash)) {
            DB::transaction(function () use ($otp) {
                $otp->increment('attempts');
                $otp->refresh();
                if ($otp->isLocked(self::MAX_ATTEMPTS)) {
                    throw new OtpLockedException(__('custom.errors.otp_locked'));
                }
            });
            throw new OtpInvalidException(__('custom.errors.otp_invalid'));
        }

        // Code is valid — consume + resolve guest
        $isPhone = str_starts_with($identifier, '+');

        $guest = DB::transaction(function () use ($otp, $identifier, $purpose, $isPhone) {
            $otp->update(['consumed_at' => now()]);

            $guest = $isPhone
                ? Guest::byPhone($identifier)->first()
                : Guest::byEmail($identifier)->first();

            if (! $guest) {
                $guest = Guest::create(
                    $isPhone
                        ? ['phone' => $identifier, 'phone_country' => 'SY']
                        : ['email' => $identifier]
                );
            }

            if ($isPhone && ! $guest->phone_verified_at) {
                $guest->markPhoneVerified();
            } elseif (! $isPhone && ! $guest->email_verified_at) {
                $guest->markEmailVerified();
            }

            // P2.5.R1: booking_link is a guarded no-op until P4.R.
            // Real booking-code -> guest linking is built in P4.R, where the real
            // Reservation model + `pending_verification` state exist. Do NOT write
            // guest_id against the P1 stub reservation (untested; semantics change under P4).
            // Throwing inside the transaction intentionally rolls back consumed_at and
            // guest create/verify for this attempt — guest must retry after P4 ships.
            if ($purpose === OtpCode::PURPOSE_BOOKING_LINK) {
                throw new BookingLinkUnavailableException(__('custom.errors.booking_link_unavailable'));
            }

            return $guest;
        });

        $token = $guest->createToken('guest')->plainTextToken;

        return [
            'data' => ['guest' => $guest, 'token' => $token],
            'code' => 200,
        ];
    }
}
