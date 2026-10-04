<?php

namespace App\Exceptions;

/**
 * Phase 9.1 (D-17): a new exchange rate differs from the current one by more
 * than 50% and `confirm_large_change` was not sent. Context: `currency`,
 * `current_rate`, `proposed_rate`, `change_percent` (decimal strings, scale 6).
 */
class ExchangeRateLargeChangeException extends DomainException
{
    public function errorCode(): string { return 'exchange_rate_large_change'; }
    public function statusCode(): int   { return 422; }
}
