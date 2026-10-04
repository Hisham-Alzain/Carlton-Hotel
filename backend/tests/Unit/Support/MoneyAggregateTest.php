<?php

namespace Tests\Unit\Support;

use App\Support\MoneyAggregate;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Phase 9 (D-21): integer cents in SQL, bcmath out, explicit driver support.
 */
class MoneyAggregateTest extends TestCase
{
    public function test_from_cents_formats_exact_two_decimal_strings(): void
    {
        $this->assertSame('0.00', MoneyAggregate::fromCents(null));
        $this->assertSame('0.00', MoneyAggregate::fromCents(0));
        $this->assertSame('0.00', MoneyAggregate::fromCents('0'));
        $this->assertSame('1.00', MoneyAggregate::fromCents(100));
        $this->assertSame('0.05', MoneyAggregate::fromCents(5));
        $this->assertSame('-0.05', MoneyAggregate::fromCents(-5));
        $this->assertSame('-12.34', MoneyAggregate::fromCents(-1234));
        $this->assertSame('9999999999.99', MoneyAggregate::fromCents('999999999999'));
    }

    public function test_from_cents_normalises_mysql_decimal_strings(): void
    {
        $this->assertSame('0.86', MoneyAggregate::fromCents('86'));
        $this->assertSame('0.86', MoneyAggregate::fromCents('86.0'));
        $this->assertSame('0.86', MoneyAggregate::fromCents('86.0000'));
        $this->assertSame('-0.86', MoneyAggregate::fromCents('-86.00'));
        $this->assertSame('0.00', MoneyAggregate::fromCents('-0'));
    }

    public function test_cents_expression_per_driver(): void
    {
        $this->assertSame('CAST(ROUND(amount_usd*100) AS INTEGER)', MoneyAggregate::centsExpression('amount_usd', 'sqlite'));
        $this->assertSame('ROUND(amount_usd*100)', MoneyAggregate::centsExpression('amount_usd', 'mysql'));
        $this->assertSame('ROUND(amount_usd*100)', MoneyAggregate::centsExpression('amount_usd', 'mariadb'));
    }

    public function test_conditional_expressions(): void
    {
        $this->assertSame(
            'CASE WHEN amount_usd > 0 THEN CAST(ROUND(amount_usd*100) AS INTEGER) ELSE 0 END',
            MoneyAggregate::positiveCentsExpression('amount_usd', 'sqlite'),
        );
        $this->assertSame(
            'CASE WHEN amount_usd < 0 THEN ROUND(amount_usd*100) ELSE 0 END',
            MoneyAggregate::negativeCentsExpression('amount_usd', 'mysql'),
        );
    }

    public function test_unsupported_driver_fails_loudly(): void
    {
        $this->expectException(LogicException::class);
        MoneyAggregate::centsExpression('amount_usd', 'pgsql');
    }
}
