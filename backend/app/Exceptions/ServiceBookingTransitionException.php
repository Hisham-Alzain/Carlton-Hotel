<?php

namespace App\Exceptions;

/** A service-booking status change outside the D-22 table; context {from, to, allowed}. */
class ServiceBookingTransitionException extends DomainException
{
    public function errorCode(): string { return 'service_booking_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
