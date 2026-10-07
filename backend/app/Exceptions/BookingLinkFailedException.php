<?php

namespace App\Exceptions;

/**
 * Thrown for every failed link-booking-code lookup (unknown code, wrong second
 * factor, booking with no contact on file) with one message and no context, so
 * a caller cannot tell which part was wrong.
 */
class BookingLinkFailedException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_link_failed';
    }

    public function statusCode(): int
    {
        return 404;
    }
}
