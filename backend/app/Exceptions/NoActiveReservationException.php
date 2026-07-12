<?php

namespace App\Exceptions;

class NoActiveReservationException extends DomainException
{
    public function errorCode(): string { return 'no_active_reservation'; }
    public function statusCode(): int   { return 403; }
}
