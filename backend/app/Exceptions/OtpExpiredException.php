<?php
namespace App\Exceptions;
class OtpExpiredException extends DomainException {
    public function errorCode(): string { return 'otp_expired'; }
    public function statusCode(): int   { return 422; }
}
