<?php

namespace App\Exceptions;

class ReservationStateException extends DomainException
{
    public function errorCode(): string { return 'reservation_state'; }
    public function statusCode(): int   { return 422; }
}
