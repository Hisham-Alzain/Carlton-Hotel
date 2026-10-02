<?php

namespace App\Exceptions;

class TicketEscalationLimitException extends DomainException
{
    public function errorCode(): string { return 'ticket_escalation_limit'; }
    public function statusCode(): int   { return 422; }
}
