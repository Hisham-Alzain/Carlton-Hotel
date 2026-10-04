<?php

namespace Tests\Unit\Guest;

use App\Support\TranslatableRules;
use Tests\TestCase;

/**
 * Phase 9.1 D-02: `guests.preferred_locale` stays `string(2)`, so every
 * configured CMS locale must fit it. Adding a region tag such as `pt_BR` to
 * CMS_LOCALES fails here before it can be truncated or rejected by MySQL.
 */
class PreferredLocaleColumnFitTest extends TestCase
{
    /**
     * Length of `guests.preferred_locale`, from
     * database/migrations/2026_07_08_100000_expand_guests_table.php
     * (`string('preferred_locale', 2)`). SQLite does not enforce varchar
     * lengths, so the migration constant is the portable source.
     */
    private const COLUMN_LENGTH = 2;

    /** @return list<string> configured locales longer than the column */
    private static function localesExceedingColumn(): array
    {
        return array_values(array_filter(
            TranslatableRules::locales(),
            fn (string $locale) => strlen($locale) > self::COLUMN_LENGTH,
        ));
    }

    public function test_every_configured_locale_fits_the_column(): void
    {
        $this->assertNotEmpty(TranslatableRules::locales());
        $this->assertSame([], self::localesExceedingColumn(), 'widen guests.preferred_locale before configuring these locales');
    }

    public function test_the_guard_reports_a_region_tag(): void
    {
        config(['cms.locales' => ['en', 'pt_BR']]);

        $this->assertSame(['pt_BR'], self::localesExceedingColumn());
    }
}
