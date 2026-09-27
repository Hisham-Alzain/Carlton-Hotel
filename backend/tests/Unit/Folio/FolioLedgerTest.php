<?php

namespace Tests\Unit\Folio;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\FolioLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 (D-03, D-07): folio money is bcmath on decimal strings; balances are
 * signed and never clamped; reservation deposits count toward the folio.
 */
class FolioLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_formats_to_two_decimals_and_never_returns_negative_zero(): void
    {
        $this->assertSame('10.50', FolioLedger::normalize('10.5'));
        $this->assertSame('3.00', FolioLedger::normalize(3));
        $this->assertSame('0.10', FolioLedger::normalize(0.1));
        $this->assertSame('0.00', FolioLedger::normalize('-0.00'));
        $this->assertSame('0.00', FolioLedger::normalize('-0'));
        $this->assertSame('-4.25', FolioLedger::normalize('-4.25'));
    }

    public function test_from_numeric_expands_exponents_and_rounds_half_away_from_zero(): void
    {
        $this->assertSame('1000.00', FolioLedger::fromNumeric('1e3'));
        $this->assertSame('1250.00', FolioLedger::fromNumeric('1.25E3'));
        $this->assertSame('0.01', FolioLedger::fromNumeric('1e-2'));
        $this->assertSame('10.56', FolioLedger::fromNumeric('10.555'));
        $this->assertSame('10.55', FolioLedger::fromNumeric('10.554'));
        $this->assertSame('-10.56', FolioLedger::fromNumeric('-10.555'));
        $this->assertSame('0.50', FolioLedger::fromNumeric('.5'));
        $this->assertSame('5.00', FolioLedger::fromNumeric('5.'));
        $this->assertSame('10.00', FolioLedger::fromNumeric(10));
        $this->assertSame('0.00', FolioLedger::fromNumeric('-0.001'));
        $this->assertSame('12.30', FolioLedger::fromNumeric(' 0012.3 '));
    }

    public function test_from_numeric_refuses_a_non_numeric_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FolioLedger::fromNumeric('abc');
    }

    public function test_sum_is_exact_at_the_one_cent_boundary(): void
    {
        $this->assertSame('0.00', FolioLedger::sum(['10.00', '-9.99', '-0.01']));
        $this->assertSame('0.00', FolioLedger::sum([]));
        // Ten float-unsafe dimes add to exactly one dollar.
        $this->assertSame('1.00', FolioLedger::sum(array_fill(0, 10, '0.10')));
        $this->assertSame('20.00', FolioLedger::sum(['19.99', '0.01']));
    }

    public function test_balance_is_signed_and_never_clamped(): void
    {
        $this->assertSame('-0.01', FolioLedger::balance('10.00', '10.01'));
        $this->assertSame('0.00', FolioLedger::balance('10.00', '10.00'));
        $this->assertSame('0.01', FolioLedger::balance('10.00', '9.99'));
    }

    public function test_paid_counts_completed_payments_only(): void
    {
        $payments = [
            (object) ['status' => 'completed', 'amount_usd' => '10.00'],
            (object) ['status' => 'pending',   'amount_usd' => '99.00'],
            (object) ['status' => 'completed', 'amount_usd' => '0.01'],
        ];

        $this->assertSame('10.01', FolioLedger::paid($payments));
    }

    public function test_ledger_payments_include_folio_and_reservation_payables_only(): void
    {
        $folio = Folio::factory()->create(['total_usd' => '100.00', 'subtotal_usd' => '100.00']);
        $other = Folio::factory()->create();

        $onFolio       = Payment::factory()->create(['payable_type' => Folio::class, 'payable_id' => $folio->id, 'amount_usd' => '30.00']);
        $onReservation = Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $folio->reservation_id, 'amount_usd' => '20.00']);
        Payment::factory()->create(['payable_type' => Folio::class, 'payable_id' => $other->id, 'amount_usd' => '500.00']);
        Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $other->reservation_id, 'amount_usd' => '500.00']);

        $ids = $folio->ledgerPayments()->pluck('id')->sort()->values()->all();
        $this->assertSame([$onFolio->id, $onReservation->id], $ids);

        // A chained filter is ANDed with the whole OR group: nothing leaks from another stay.
        $this->assertSame(0, $folio->ledgerPayments()->where('amount_usd', '500.00')->count());

        $this->assertSame('50.00', $folio->paidUsd());
        $this->assertSame('50.00', $folio->balanceDueUsd());
    }

    public function test_balance_due_counts_reservation_deposits_and_goes_negative(): void
    {
        $folio = Folio::factory()->create(['total_usd' => '10.00', 'subtotal_usd' => '10.00']);
        Payment::factory()->create(['payable_type' => Reservation::class, 'payable_id' => $folio->reservation_id, 'amount_usd' => '12.50']);
        Payment::factory()->create(['payable_type' => Folio::class, 'payable_id' => $folio->id, 'amount_usd' => '5.00', 'status' => 'failed']);

        $this->assertSame('12.50', $folio->paidUsd());
        $this->assertSame('-2.50', $folio->balanceDueUsd());
    }

    public function test_recalculate_totals_folds_stored_rows_exactly(): void
    {
        $folio = Folio::factory()->create();
        FolioItem::factory()->create(['folio_id' => $folio->id, 'amount_usd' => '19.99']);
        FolioItem::factory()->manual()->create(['folio_id' => $folio->id, 'amount_usd' => '0.01', 'unit_price_usd' => '0.01']);
        FolioItem::factory()->credit()->create(['folio_id' => $folio->id, 'amount_usd' => '-10.00']);
        foreach (range(1, 10) as $i) {
            FolioItem::factory()->manual()->create(['folio_id' => $folio->id, 'amount_usd' => '0.10', 'unit_price_usd' => '0.10']);
        }

        $this->assertSame('11.00', $folio->recalculateTotals());

        $fresh = $folio->fresh();
        $this->assertSame('11.00', $fresh->subtotal_usd);
        $this->assertSame('11.00', $fresh->total_usd);
    }

    public function test_recalculate_totals_on_an_empty_folio_is_zero(): void
    {
        $folio = Folio::factory()->create(['subtotal_usd' => '5.00', 'total_usd' => '5.00']);

        $this->assertSame('0.00', $folio->recalculateTotals());
        $this->assertSame('0.00', $folio->fresh()->total_usd);
    }
}
