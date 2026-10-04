<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The one calculator shared by preview, booking, earn and reports (Q10);
 * never floats.
 *
 * Money and rates travel as decimal strings and every operation is bcmath, so
 * the same inputs give the same answer wherever they are computed. Points are
 * rounded half-up (add one half, then truncate); a discount is rounded half-up
 * to the cent; a cap is truncated to the cent so the hotel never exceeds it.
 * Style precedent: {@see FolioLedger}.
 */
final class LoyaltyMath
{
    /**
     * The only float boundary: an external quote (float, int or numeric string)
     * becomes a two-decimal USD string.
     */
    public static function fromQuote(int|float|string $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Half-up integer of a non-negative decimal string: 2.5 gives 3, 2.4999999
     * gives 2.
     *
     * @throws InvalidArgumentException when the value is negative
     */
    public static function halfUpInt(string $nonNegative): int
    {
        self::assertNonNegative($nonNegative);

        return (int) bcadd(bcadd($nonNegative, '0.5', 6), '0', 0);
    }

    /**
     * Points earned for a spend: spend x rate, exact at scale 6, then half-up.
     *
     * @throws InvalidArgumentException when the spend or the rate is negative
     */
    public static function pointsForSpend(string $spendUsd, string $earnRate): int
    {
        self::assertNonNegative($spendUsd);
        self::assertNonNegative($earnRate);

        return self::halfUpInt(bcmul($spendUsd, $earnRate, 6));
    }

    /**
     * The discount a number of points buys: points x value, half-up to the cent.
     *
     * @throws InvalidArgumentException when the points or the value are negative
     */
    public static function discountForPoints(int $points, string $redeemValueUsd): string
    {
        if ($points < 0) {
            throw new InvalidArgumentException('Points cannot be negative.');
        }
        self::assertNonNegative($redeemValueUsd);

        return bcadd(bcmul((string) $points, $redeemValueUsd, 6), '0.005', 2);
    }

    /**
     * The most of a total that points may pay: total x percent / 100, truncated
     * to the cent.
     *
     * @throws InvalidArgumentException when the total or the percent is negative
     */
    public static function maxDiscount(string $totalUsd, string $percent): string
    {
        self::assertNonNegative($totalUsd);
        self::assertNonNegative($percent);

        return bcadd(bcdiv(bcmul($totalUsd, $percent, 6), '100', 6), '0', 2);
    }

    /**
     * The largest n with discountForPoints(n) <= cap.
     *
     * @throws InvalidArgumentException when an input is negative or the value is zero
     */
    public static function maxPointsForCap(string $capUsd, string $redeemValueUsd): int
    {
        self::assertNonNegative($capUsd);
        self::assertNonNegative($redeemValueUsd);
        if (bccomp($redeemValueUsd, '0', 6) === 0) {
            throw new InvalidArgumentException('The redeem value must be positive.');
        }
        if (bccomp($capUsd, '0', 2) <= 0) {
            return 0;
        }

        $points = (int) bcdiv($capUsd, $redeemValueUsd, 0);

        // Half-up rounding can sit one cent either side of the plain quotient.
        while (bccomp(self::discountForPoints($points + 1, $redeemValueUsd), $capUsd, 2) <= 0) {
            $points++;
        }
        while ($points > 0 && bccomp(self::discountForPoints($points, $redeemValueUsd), $capUsd, 2) > 0) {
            $points--;
        }

        return $points;
    }

    /** The smaller of two USD amounts, as a two-decimal string. */
    public static function minUsd(string $a, string $b): string
    {
        return bcadd(bccomp($a, $b, 2) <= 0 ? $a : $b, '0', 2);
    }

    /** Gross minus discount, never below zero. */
    public static function netTotal(string $grossUsd, string $discountUsd): string
    {
        $net = bcsub($grossUsd, $discountUsd, 2);

        return bccomp($net, '0', 2) < 0 ? '0.00' : $net;
    }

    private static function assertNonNegative(string $value): void
    {
        if (bccomp($value, '0', 6) < 0) {
            throw new InvalidArgumentException("Negative value not allowed: {$value}");
        }
    }
}
