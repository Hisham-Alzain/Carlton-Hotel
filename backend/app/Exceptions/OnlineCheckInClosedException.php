<?php

namespace App\Exceptions;

/** Thrown when the hotel-local date is after check_in (D-10); context {check_in, today}. */
class OnlineCheckInClosedException extends DomainException
{
    public function errorCode(): string { return 'online_check_in_closed'; }
    public function statusCode(): int   { return 422; }
}
