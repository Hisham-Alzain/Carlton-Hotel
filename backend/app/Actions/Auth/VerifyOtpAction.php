<?php
namespace App\Actions\Auth;

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

            // Handle booking_link purpose: link reservation
            if ($purpose === OtpCode::PURPOSE_BOOKING_LINK) {
                \App\Models\Reservation::where('phone', $identifier)
                    ->orWhere(function ($q) use ($guest) {
                        $q->where('guest_id', null)->where('phone', $guest->phone);
                    })
                    ->whereNull('guest_id')
                    ->update(['guest_id' => $guest->id]);
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
