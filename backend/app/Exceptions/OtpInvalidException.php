<?php
namespace App\Exceptions;
class OtpInvalidException extends DomainException {
    public function errorCode(): string { return 'otp_invalid'; }
    public function statusCode(): int   { return 422; }
}
