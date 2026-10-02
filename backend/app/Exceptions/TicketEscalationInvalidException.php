<?php

namespace App\Exceptions;

class TicketEscalationInvalidException extends DomainException
{
    public function errorCode(): string { return 'ticket_escalation_invalid'; }
    public function statusCode(): int   { return 422; }
}
