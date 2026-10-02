<?php

namespace App\Exceptions;

class TicketClosedException extends DomainException
{
    public function errorCode(): string { return 'ticket_closed'; }
    public function statusCode(): int   { return 422; }
}
