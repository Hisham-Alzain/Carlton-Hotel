<?php

namespace Tests\Unit\Currency;

use App\Support\CurrencyConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 9.1 (D-14): config/currency.php read through a fail-closed accessor.
 */
class CurrencyConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $this->assertSame('USD', CurrencyConfig::base());
        $this->assertSame(['SYP', 'TRY'], CurrencyConfig::codes());
        $this->assertSame(0, CurrencyConfig::displayDecimals('SYP'));
        $this->assertSame(2, CurrencyConfig::displayDecimals('TRY'));
        $this->assertSame(168, CurrencyConfig::staleAfterHours());
    }

    public function test_stale_hours_follow_config(): void
    {
        config(['currency.stale_after_hours' => 24]);

        $this->assertSame(24, CurrencyConfig::staleAfterHours());
    }

    public function test_an_unconfigured_code_has_no_decimals(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EUR');

        CurrencyConfig::displayDecimals('EUR');
    }

    /** @return array<string, array{array, string}> */
    public static function badCurrencySets(): array
    {
        return [
            'empty'          => [[], 'empty'],
            'lower case'     => [['syp' => ['display_decimals' => 0]], 'syp'],
            'base as quote'  => [['USD' => ['display_decimals' => 2]], 'USD'],
            'four letters'   => [['SYPP' => ['display_decimals' => 0]], 'SYPP'],
            'decimals 7'     => [['SYP' => ['display_decimals' => 7]], 'SYP'],
            'decimals -1'    => [['SYP' => ['display_decimals' => -1]], 'SYP'],
            'no decimals'    => [['SYP' => []], 'SYP'],
        ];
    }

    #[DataProvider('badCurrencySets')]
    public function test_bad_currency_sets_fail_closed(array $currencies, string $named): void
    {
        config(['currency.currencies' => $currencies]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($named);

        CurrencyConfig::codes();
    }

    public function test_zero_stale_hours_fail_closed(): void
    {
        config(['currency.stale_after_hours' => 0]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stale_after_hours');

        CurrencyConfig::staleAfterHours();
    }

    public function test_a_base_other_than_usd_fails_closed(): void
    {
        config(['currency.base' => 'EUR']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EUR');

        CurrencyConfig::base();
    }
}
