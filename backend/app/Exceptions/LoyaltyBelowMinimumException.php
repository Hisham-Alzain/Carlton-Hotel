<?php

namespace App\Exceptions;

/** Phase 10 (Q4, Q16): the points to redeem are below the configured minimum. */
class LoyaltyBelowMinimumException extends DomainException
{
    public function errorCode(): string { return 'loyalty_below_minimum'; }
    public function statusCode(): int   { return 422; }
}
