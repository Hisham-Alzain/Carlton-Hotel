<?php

namespace App\Exceptions;

/** Phase 8 (D-17, D-28): one deposit per inquiry; context `payment_uuid`, `paid_at`. */
class EventDepositAlreadyRecordedException extends DomainException
{
    public function errorCode(): string { return 'event_deposit_already_recorded'; }
    public function statusCode(): int   { return 422; }
}
