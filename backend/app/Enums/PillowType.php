<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * A guest's pillow preference (Phase 4, D-08).
 *
 * `hypoallergenic` is allergy-adjacent, so the whole value is kept out of
 * activity_log (`Guest::getActivitylogOptions()` → logExcept).
 */
enum PillowType: string
{
    use HasValues;

    case SOFT           = 'soft';
    case MEDIUM         = 'medium';
    case FIRM           = 'firm';
    case FEATHER        = 'feather';
    case HYPOALLERGENIC = 'hypoallergenic';
}
