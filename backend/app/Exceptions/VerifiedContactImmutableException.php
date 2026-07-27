<?php

namespace App\Exceptions;

/**
 * Raised when a guest tries to overwrite a phone or email they already verified.
 * Changing a verified login identifier has to go back through OTP.
 */
class VerifiedContactImmutableException extends DomainException
{
    public function errorCode(): string { return 'verified_contact_immutable'; }
    public function statusCode(): int   { return 409; }
}
