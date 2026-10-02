<?php

namespace App\Exceptions;

class TicketTransitionException extends DomainException
{
    public function errorCode(): string { return 'ticket_transition_invalid'; }
    public function statusCode(): int   { return 422; }
}
