<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Status of a night-audit blocker (Phase 9, D-06). Blockers have no override:
 * resolving one is a noted attestation, not a live re-check.
 */
enum NightAuditBlockerStatus: string
{
    use HasValues;

    case OPEN     = 'open';
    case RESOLVED = 'resolved';
}
