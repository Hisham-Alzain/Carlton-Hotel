<?php

namespace App\Exceptions;

/** Phase 5 (D-05 item floor: context item_uuid, remaining_usd, amount_usd). */
class FolioCreditExceedsItemException extends DomainException
{
    public function errorCode(): string { return 'folio_credit_exceeds_item'; }
    public function statusCode(): int   { return 422; }
}
