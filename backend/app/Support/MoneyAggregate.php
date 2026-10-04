<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Exact money aggregation in SQL (Phase 9, D-21).
 *
 * SQLite returns REAL for `SUM(DECIMAL)` (the same drift `Folio::refreshTotals`
 * works around), so every report sum is taken over per-row **integer cents**
 * and formatted back with bcmath. Stored values are DECIMAL(10,2), so
 * `ROUND(col*100)` per row is exact; signed 64-bit cents cannot overflow at
 * hotel scale over a ≤ 31-day window.
 *
 * The `$column` argument is always a code constant (e.g. `'amount_usd'`); user
 * input never reaches these expressions. Unsupported drivers fail loudly.
 */
final class MoneyAggregate
{
    /** Per-row integer cents of a DECIMAL(…,2) column. */
    public static function centsExpression(string $column, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite'           => "CAST(ROUND({$column}*100) AS INTEGER)",
            'mysql', 'mariadb' => "ROUND({$column}*100)",
            default            => throw new LogicException("MoneyAggregate: unsupported driver {$driver}"),
        };
    }

    /** Cents of positive rows only (charges); 0 otherwise. */
    public static function positiveCentsExpression(string $column, ?string $driver = null): string
    {
        return "CASE WHEN {$column} > 0 THEN " . self::centsExpression($column, $driver) . ' ELSE 0 END';
    }

    /** Cents of negative rows only (credits); 0 otherwise. */
    public static function negativeCentsExpression(string $column, ?string $driver = null): string
    {
        return "CASE WHEN {$column} < 0 THEN " . self::centsExpression($column, $driver) . ' ELSE 0 END';
    }

    /**
     * Integer cents (an int, or the string MySQL/PDO returns, possibly with a
     * zero fraction such as "8600.00") to an exact 2dp USD string. Never a float.
     */
    public static function fromCents(int|string|null $cents): string
    {
        if ($cents === null) {
            return '0.00';
        }

        $result = bcdiv(bcadd((string) $cents, '0', 0), '100', 2);

        // bcmath keeps the sign of a zero ("-0.00"); a report never shows one.
        return bccomp($result, '0', 2) === 0 ? '0.00' : $result;
    }
}
