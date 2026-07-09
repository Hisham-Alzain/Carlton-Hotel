<?php

namespace App\Exceptions;

class NoAvailabilityException extends DomainException
{
    public function errorCode(): string { return 'no_availability'; }
    public function statusCode(): int   { return 409; }
}
