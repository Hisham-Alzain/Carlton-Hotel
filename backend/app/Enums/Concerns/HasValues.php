<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for the backed string enums in App\Enums.
 *
 * Gives every enum a single source of truth for its value list, so validation
 * rules, query scopes and API payloads never restate the strings by hand.
 */
trait HasValues
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Human-readable label derived from the backing value. */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return array_combine(
            self::values(),
            array_map(static fn (self $case) => $case->label(), self::cases()),
        );
    }
}
