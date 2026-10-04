<?php

namespace App\Exceptions;

/** Phase 10 (Q4, Q16): the points would pay more of the booking than the redeem cap allows. */
class LoyaltyOverCapException extends DomainException
{
    public function errorCode(): string { return 'loyalty_over_cap'; }
    public function statusCode(): int   { return 422; }
}
