<?php
namespace App\Exceptions;
class OtpLockedException extends DomainException {
    public function errorCode(): string { return 'otp_locked'; }
    public function statusCode(): int   { return 429; }
}
