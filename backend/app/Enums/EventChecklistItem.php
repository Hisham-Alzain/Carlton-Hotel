<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The fixed staff checklist of an event inquiry (Phase 8, D-02/D-03). The
 * values are the dashboard's item ids. Rows exist only for ticked items
 * (lazy); every read merges this template with them, in case order.
 *
 * `deposit` is derived from the inquiry's deposit state (D-04) and is never
 * stored or toggled directly.
 */
enum EventChecklistItem: string
{
    use HasValues;

    case CONTRACT  = 'contract';
    case DEPOSIT   = 'deposit';
    case GUARANTEE = 'guarantee';
    case BEO       = 'beo';
    case AV        = 'av';

    public function label(): string
    {
        return __('custom.event_checklist.' . $this->value);
    }

    public function ownerDepartment(): Department
    {
        return match ($this) {
            self::CONTRACT, self::GUARANTEE => Department::SALES,
            self::DEPOSIT, self::BEO        => Department::EVENTS,
            self::AV                        => Department::MAINTENANCE,
        };
    }

    public function isDerived(): bool
    {
        return $this === self::DEPOSIT;
    }
}
