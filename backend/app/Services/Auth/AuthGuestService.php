<?php
namespace App\Services\Auth;

use App\Actions\Auth\LinkBookingCodeAction;
use App\Actions\Auth\RequestOtpAction;
use App\Actions\Auth\UpdateGuestProfileAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;

class AuthGuestService
{
    public function __construct(
        private readonly RequestOtpAction     $requestOtp,
        private readonly VerifyOtpAction      $verifyOtp,
        private readonly LinkBookingCodeAction $linkBookingCode,
        private readonly UpdateGuestProfileAction $updateProfile,
    ) {}

    public function requestOtp(string $identifier, OtpChannel|string $channel, OtpPurpose|string $purpose): array
    {
        return $this->requestOtp->handle($identifier, $channel, $purpose);
    }

    public function verifyOtp(string $identifier, string $code, OtpPurpose|string $purpose, ?string $bookingCode = null): array
    {
        return $this->verifyOtp->handle($identifier, $code, $purpose, $bookingCode);
    }

    public function linkBookingCode(string $code, ?string $lastName, ?string $phone): array
    {
        return $this->linkBookingCode->handle($code, $lastName, $phone);
    }

    public function me(Guest $guest): array
    {
        $guest->load('activeReservations');
        return ['data' => $guest, 'code' => 200];
    }

    public function updateProfile(Guest $guest, array $data): array
    {
        return $this->updateProfile->handle($guest, $data);
    }

    public function logout(Guest $guest, ?string $deviceToken = null): array
    {
        DB::transaction(function () use ($guest, $deviceToken) {
            $token = $guest->currentAccessToken();
            if ($token) {
                $token->delete();
            } else {
                // Fallback: delete all tokens for this guest (safe — one active session)
                $guest->tokens()->delete();
            }

            // Scoped through the relation, so guest_id is always in the WHERE:
            // a token owned by another guest, or unknown, deletes nothing.
            if ($deviceToken !== null && $deviceToken !== '') {
                $guest->deviceTokens()->where('token', $deviceToken)->delete();
            }
        });

        return ['data' => null, 'code' => 200];
    }
}
