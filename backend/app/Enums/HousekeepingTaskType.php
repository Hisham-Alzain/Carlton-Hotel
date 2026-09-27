<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** Kind of housekeeping task (Phase 6, D-01). */
enum HousekeepingTaskType: string
{
    use HasValues;

    case TURNOVER   = 'turnover';
    case STAYOVER   = 'stayover';
    case INSPECTION = 'inspection';
    case REQUEST    = 'request';

    /**
     * The types that carry a room dedupe key (one open task per room and type,
     * D-02) and the only ones staff may create by hand (D-08). Request tasks
     * dedupe on their service request instead.
     *
     * @return list<self>
     */
    public static function deduped(): array
    {
        return [self::TURNOVER, self::STAYOVER, self::INSPECTION];
    }

    public function isDeduped(): bool
    {
        return in_array($this, self::deduped(), true);
    }
}
