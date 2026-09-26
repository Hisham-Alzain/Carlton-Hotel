<?php

namespace App\Exceptions;

class ReservationOutsideStayWindowException extends DomainException
{
    public function errorCode(): string { return 'reservation_outside_stay_window'; }
    public function statusCode(): int   { return 422; }
}
