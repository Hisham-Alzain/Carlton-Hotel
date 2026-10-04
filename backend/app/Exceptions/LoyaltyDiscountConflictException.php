<?php

namespace App\Exceptions;

/** Phase 10 (Q16): a booking asks for points and a voucher together, or a second voucher. */
class LoyaltyDiscountConflictException extends DomainException
{
    public function errorCode(): string { return 'loyalty_discount_conflict'; }
    public function statusCode(): int   { return 422; }
}
