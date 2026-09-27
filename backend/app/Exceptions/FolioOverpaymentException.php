<?php

namespace App\Exceptions;

/** Phase 5 (D-13: context balance_due_usd, amount_usd). */
class FolioOverpaymentException extends DomainException
{
    public function errorCode(): string { return 'folio_overpayment'; }
    public function statusCode(): int   { return 422; }
}
