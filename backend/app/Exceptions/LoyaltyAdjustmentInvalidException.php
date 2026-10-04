<?php

namespace App\Exceptions;

/** Phase 10 (Q16): a manual points adjustment is zero, over the limit or would take the balance below zero. */
class LoyaltyAdjustmentInvalidException extends DomainException
{
    public function errorCode(): string { return 'loyalty_adjustment_invalid'; }
    public function statusCode(): int   { return 422; }
}
