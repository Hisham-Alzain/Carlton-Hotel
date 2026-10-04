<?php

namespace App\Exceptions;

/** Phase 10 (Q16): a redemption asks for more points than the guest's unexpired balance. */
class LoyaltyInsufficientPointsException extends DomainException
{
    public function errorCode(): string { return 'loyalty_insufficient_points'; }
    public function statusCode(): int   { return 422; }
}
