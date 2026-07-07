<?php
namespace App\Services\Auth;

use App\Actions\Auth\LinkBookingCodeAction;
use App\Actions\Auth\RequestOtpAction;
use App\Actions\Auth\VerifyOtpAction;

class AuthGuestService
{
    public function __construct(
        private readonly RequestOtpAction     $requestOtp,
        private readonly VerifyOtpAction      $verifyOtp,
        private readonly LinkBookingCodeAction $linkBookingCode,
    ) {}

    public function requestOtp(string $identifier, string $channel, string $purpose): array
    {
        return $this->requestOtp->handle($identifier, $channel, $purpose);
    }

    public function verifyOtp(string $identifier, string $code, string $purpose): array
    {
        return $this->verifyOtp->handle($identifier, $code, $purpose);
    }

    public function linkBookingCode(string $code, ?string $lastName, ?string $phone): array
    {
        return $this->linkBookingCode->handle($code, $lastName, $phone);
    }
}
