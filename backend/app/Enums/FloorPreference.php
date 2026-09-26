<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * A guest's floor preference (Phase 4, D-08).
 *
 * `any` means the guest declared no preference; it still counts as a choice
 * for the pre-arrival checklist's `preferences_set` item (D-05).
 */
enum FloorPreference: string
{
    use HasValues;

    case LOW  = 'low';
    case HIGH = 'high';
    case ANY  = 'any';
}
