<?php
namespace App\Exceptions;

class BookingLinkUnavailableException extends DomainException
{
    public function errorCode(): string { return 'booking_link_unavailable'; }
    public function statusCode(): int   { return 422; }
}
