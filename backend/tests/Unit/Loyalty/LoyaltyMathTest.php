<?php

namespace Tests\Unit\Loyalty;

use App\Support\LoyaltyMath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phase 10 (Q10, Pitfall 7): the one calculator shared by preview, booking,
 * earn and reports. Every result is asserted for type AND exact value, so a
 * float sneaking in or a half-even round fails loudly at the edges.
 */
class LoyaltyMathTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function halfUpCases(): array
    {
        return [
            'zero' => ['0', 0],
            'exact half rounds up' => ['2.5', 3],
            'just under half rounds down' => ['2.4999999', 2],
            'half of zero' => ['0.5', 1],
            'large exact half' => ['1234567.500000', 1234568],
            'whole number untouched' => ['42', 42],
        ];
    }

    #[DataProvider('halfUpCases')]
    public function test_half_up_int(string $input, int $expected): void
    {
        $result = LoyaltyMath::halfUpInt($input);

        $this->assertIsInt($result);
        $this->assertSame($expected, $result);
    }

    public function test_half_up_int_rejects_a_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoyaltyMath::halfUpInt('-0.1');
    }

    /** @return array<string, array{string, string, int}> */
    public static function earnCases(): array
    {
        return [
            'rate one' => ['300.00', '1.0000', 300],
            'rate 0.05 on 30.00 is 1.5 and rounds up' => ['30.00', '0.0500', 2],
            'rate 0.05 on 9.90 is 0.495 and rounds down' => ['9.90', '0.0500', 0],
            'maximum column values are exact' => ['99999999.99', '9999.9999', 999999989900],
            'zero spend' => ['0.00', '2.0000', 0],
        ];
    }

    #[DataProvider('earnCases')]
    public function test_points_for_spend(string $spend, string $rate, int $expected): void
    {
        $result = LoyaltyMath::pointsForSpend($spend, $rate);

        $this->assertIsInt($result);
        $this->assertSame($expected, $result);
    }

    public function test_points_for_spend_rejects_a_negative_spend(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoyaltyMath::pointsForSpend('-1.00', '1.0000');
    }

    /** @return array<string, array{int, string, string}> */
    public static function discountCases(): array
    {
        return [
            '100 points at one cent' => [100, '0.0100', '1.00'],
            'half a cent rounds to a cent' => [1, '0.0050', '0.01'],
            'under half a cent rounds to zero' => [1, '0.0049', '0.00'],
            '4.995 rounds up' => [333, '0.0150', '5.00'],
            'zero points' => [0, '0.0100', '0.00'],
        ];
    }

    #[DataProvider('discountCases')]
    public function test_discount_for_points(int $points, string $value, string $expected): void
    {
        $result = LoyaltyMath::discountForPoints($points, $value);

        $this->assertIsString($result);
        $this->assertSame($expected, $result);
    }

    public function test_discount_for_points_rejects_negative_points(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoyaltyMath::discountForPoints(-1, '0.0100');
    }

    /** @return array<string, array{string, string, string}> */
    public static function maxDiscountCases(): array
    {
        return [
            'floors to the cent' => ['333.33', '33.33', '111.09'],
            'full cap' => ['100.00', '100.00', '100.00'],
            'zero total' => ['0.00', '50.00', '0.00'],
        ];
    }

    #[DataProvider('maxDiscountCases')]
    public function test_max_discount(string $total, string $percent, string $expected): void
    {
        $result = LoyaltyMath::maxDiscount($total, $percent);

        $this->assertIsString($result);
        $this->assertSame($expected, $result);
    }

    public function test_max_discount_rejects_a_negative_total(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoyaltyMath::maxDiscount('-5.00', '50.00');
    }

    /** @return array<string, array{string, string, int}> */
    public static function maxPointsCases(): array
    {
        return [
            '66 gives 0.99 and 67 gives 1.01' => ['1.00', '0.0150', 66],
            'zero cap' => ['0.00', '0.0100', 0],
            'five dollars at a cent' => ['5.00', '0.0100', 500],
        ];
    }

    #[DataProvider('maxPointsCases')]
    public function test_max_points_for_cap(string $cap, string $value, int $expected): void
    {
        $result = LoyaltyMath::maxPointsForCap($cap, $value);

        $this->assertIsInt($result);
        $this->assertSame($expected, $result);
        $this->assertLessThanOrEqual(0, bccomp(LoyaltyMath::discountForPoints($result, $value), $cap, 2));
        $this->assertSame(1, bccomp(LoyaltyMath::discountForPoints($result + 1, $value), $cap, 2));
    }

    public function test_from_quote_converts_the_only_float_boundary(): void
    {
        $this->assertSame('200.00', LoyaltyMath::fromQuote(199.999));
        $this->assertSame('150.00', LoyaltyMath::fromQuote(150));
        $this->assertSame('75.50', LoyaltyMath::fromQuote('75.5'));
        $this->assertSame('1234.50', LoyaltyMath::fromQuote(1234.5));
    }

    public function test_min_usd_and_net_total(): void
    {
        $this->assertSame('9.99', LoyaltyMath::minUsd('10.00', '9.99'));
        $this->assertSame('9.99', LoyaltyMath::minUsd('9.99', '10.00'));
        $this->assertSame('0.00', LoyaltyMath::netTotal('50.00', '60.00'));
        $this->assertSame('99.50', LoyaltyMath::netTotal('120.00', '20.50'));
        $this->assertIsString(LoyaltyMath::netTotal('1.00', '0.00'));
    }
}
