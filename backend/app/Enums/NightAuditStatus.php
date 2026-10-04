<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Night audit lifecycle (Phase 9, D-07). There is no reopen. */
enum NightAuditStatus: string
{
    use HasValues;

    case OPEN   = 'open';
    case CLOSED = 'closed';
}
