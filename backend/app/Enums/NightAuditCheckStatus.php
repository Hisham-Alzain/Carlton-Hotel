<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Status of one night-audit check (Phase 9, D-06). An empty category is
 * `passed` at open; a non-empty one is `pending` until staff record it as
 * `resolved` (fixed in the source) or `overridden` (exception accepted).
 */
enum NightAuditCheckStatus: string
{
    use HasValues;

    case PASSED     = 'passed';
    case PENDING    = 'pending';
    case RESOLVED   = 'resolved';
    case OVERRIDDEN = 'overridden';

    public function isTerminal(): bool
    {
        return $this !== self::PENDING;
    }
}
