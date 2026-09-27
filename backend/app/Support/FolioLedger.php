<?php

namespace App\Support;

/**
 * The only place folio money is added or subtracted (D-03). bcmath on decimal
 * strings, never floats; PHP 8.3 compatible (no PHP 8.4-only bcmath API).
 *
 * Every value entering bcadd/bcsub goes through normalize() first: Eloquent's
 * `decimal:2` cast already returns an exact string, but factories and tests may
 * hand in ints.
 */
final class FolioLedger
{
    private const SCALE = 2;

    /**
     * Format an already-validated value to 2 decimals. Never does arithmetic on
     * a float: the value is stringified and passed to bcmath as-is.
     */
    public static function normalize(string|int|float $value): string
    {
        $result = bcadd((string) $value, '0', self::SCALE);

        // bcmath keeps the sign of a zero ("-0.00"); a ledger never shows one.
        return bccomp($result, '0', self::SCALE) === 0 ? '0.00' : $result;
    }

    /**
     * Exact 2dp string for a value validated only as `numeric` (the legacy
     * reservation settle route). Unlike normalize() it accepts exponent notation
     * ("1e3") and rounds half away from zero ("10.555" -> "10.56", as a DECIMAL(10,2)
     * column stores it) instead of truncating. No float is involved.
     */
    public static function fromNumeric(string|int|float $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $value, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new \InvalidArgumentException("Not a numeric amount: {$value}");
        }

        $sign     = $m[1] === '-' ? '-' : '';
        $digits   = ltrim($m[2], '0').($m[3] ?? '');
        $point    = strlen(ltrim($m[2], '0')) + (int) ($m[4] ?? 0);
        $digits   = $point < 0 ? str_repeat('0', -$point).$digits : $digits;
        $point    = max($point, 0);
        $digits   = str_pad($digits, $point, '0');
        $int      = substr($digits, 0, $point);
        $exact    = $sign.($int === '' ? '0' : $int).'.'.(substr($digits, $point) ?: '0');

        // bcadd truncates toward zero at the scale, so add half a cent with the
        // value's own sign first: round half away from zero.
        $rounded = bcadd($exact, $sign.'0.005', self::SCALE);

        return self::normalize($rounded);
    }

    /** @param iterable<string|int|float> $amounts */
    public static function sum(iterable $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, self::normalize($amount), self::SCALE);
        }

        return self::normalize($total);
    }

    /**
     * Sum of `amount_usd` over the completed payments in the given list.
     *
     * @param iterable<\App\Models\Payment|object> $payments
     */
    public static function paid(iterable $payments): string
    {
        $amounts = [];

        foreach ($payments as $payment) {
            if ($payment->status === 'completed') {
                $amounts[] = $payment->amount_usd;
            }
        }

        return self::sum($amounts);
    }

    /** Signed balance: never clamped, never "-0.00". */
    public static function balance(string $total, string $paid): string
    {
        return self::normalize(bcsub(self::normalize($total), self::normalize($paid), self::SCALE));
    }
}
