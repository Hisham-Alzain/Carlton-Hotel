<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads `config/currency.php` (Phase 9.1, D-14). Like TranslatableRules it
 * carries no fallback and fails closed: a malformed currency set is a
 * misconfiguration, and silently substituting one would publish rates for a
 * set nobody configured.
 */
final class CurrencyConfig
{
    /** Money is DECIMAL USD everywhere (`*_usd`); the base is not configurable. */
    private const BASE = 'USD';

    private const CODE_PATTERN = '/^[A-Z]{3}$/';

    private const MAX_DISPLAY_DECIMALS = 6;

    /** @throws RuntimeException when `currency.base` is not USD */
    public static function base(): string
    {
        $base = config('currency.base');

        if ($base !== self::BASE) {
            throw new RuntimeException(sprintf('currency.base must be %s, got %s.', self::BASE, var_export($base, true)));
        }

        return $base;
    }

    /**
     * Configured display currencies, in config order.
     *
     * @return list<string>
     *
     * @throws RuntimeException when the set is empty or any entry is malformed
     */
    public static function codes(): array
    {
        return array_keys(self::currencies());
    }

    /** @throws RuntimeException when the code is not configured */
    public static function displayDecimals(string $code): int
    {
        $currencies = self::currencies();

        if (! array_key_exists($code, $currencies)) {
            throw new RuntimeException(sprintf('Currency %s is not configured in currency.currencies.', $code));
        }

        return $currencies[$code];
    }

    /** @throws RuntimeException when below 1 */
    public static function staleAfterHours(): int
    {
        $hours = config('currency.stale_after_hours');

        if (! is_int($hours) || $hours < 1) {
            throw new RuntimeException(sprintf('currency.stale_after_hours must be an integer >= 1, got %s.', var_export($hours, true)));
        }

        return $hours;
    }

    /** @return array<string, int> code => display decimals */
    private static function currencies(): array
    {
        $raw = config('currency.currencies');

        if (! is_array($raw) || $raw === []) {
            throw new RuntimeException('currency.currencies is empty; configure at least one display currency.');
        }

        $out = [];
        foreach ($raw as $code => $settings) {
            if (! is_string($code) || ! preg_match(self::CODE_PATTERN, $code)) {
                throw new RuntimeException(sprintf('currency.currencies key %s is not an upper-case ISO 4217 code.', var_export($code, true)));
            }
            if ($code === self::BASE) {
                throw new RuntimeException(sprintf('currency.currencies lists %s, which is the base currency.', $code));
            }

            $decimals = is_array($settings) ? ($settings['display_decimals'] ?? null) : null;
            if (! is_int($decimals) || $decimals < 0 || $decimals > self::MAX_DISPLAY_DECIMALS) {
                throw new RuntimeException(sprintf('currency.currencies.%s.display_decimals must be an integer 0..%d.', $code, self::MAX_DISPLAY_DECIMALS));
            }

            $out[$code] = $decimals;
        }

        return $out;
    }
}
