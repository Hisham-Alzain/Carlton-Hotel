<?php

namespace App\Exceptions;

class TicketRecoveryFolioInvalidException extends DomainException
{
    public function errorCode(): string { return 'ticket_recovery_folio_invalid'; }
    public function statusCode(): int   { return 422; }
}
