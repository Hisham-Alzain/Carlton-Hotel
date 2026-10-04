<?php

namespace App\Exceptions;

/** Phase 10 (Q16, T-10-09): one uniform answer for unknown, foreign, used or expired codes - it never reveals which. */
class LoyaltyVoucherInvalidException extends DomainException
{
    public function errorCode(): string { return 'loyalty_voucher_invalid'; }
    public function statusCode(): int   { return 422; }
}
