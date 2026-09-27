<?php

namespace App\Exceptions;

/** Phase 5 (D-05 whole-folio floor: context balance_due_usd, amount_usd). */
class FolioCreditExceedsBalanceException extends DomainException
{
    public function errorCode(): string { return 'folio_credit_exceeds_balance'; }
    public function statusCode(): int   { return 422; }
}
