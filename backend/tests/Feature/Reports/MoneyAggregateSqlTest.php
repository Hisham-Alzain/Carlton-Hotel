<?php

namespace Tests\Feature\Reports;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Support\MoneyAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9 (D-21): SQLite returns REAL for SUM(DECIMAL); per-row integer cents
 * summed in SQL and formatted with bcmath stay exact.
 */
class MoneyAggregateSqlTest extends TestCase
{
    use RefreshDatabase;

    private Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folio = Folio::factory()->create();
    }

    public function test_empty_set_is_zero(): void
    {
        $this->assertSame('0.00', $this->net());
    }

    public function test_ten_dimes_make_one_dollar(): void
    {
        $this->seedAmounts(array_fill(0, 10, '0.10'));
        $this->assertSame('1.00', $this->net());
    }

    public function test_float_edge_values(): void
    {
        $this->seedAmounts(['0.29', '0.57']);
        $this->assertSame('0.86', $this->net());
    }

    public function test_mixed_signs(): void
    {
        $this->seedAmounts(['100.10', '-40.05']);
        $this->assertSame('60.05', $this->net());
    }

    public function test_only_negatives(): void
    {
        $this->seedAmounts(['-0.10', '-0.20', '-10.01']);
        $this->assertSame('-10.31', $this->net());
    }

    public function test_near_max_values_over_many_rows(): void
    {
        $this->seedAmounts(array_fill(0, 50, '99999999.99'));
        $this->assertSame('4999999999.50', $this->net());
    }

    public function test_conditional_charges_and_credits_add_up_to_net(): void
    {
        $this->seedAmounts(['0.29', '0.57', '-0.10', '120.00', '-33.33', '0.01']);

        $row = DB::table('folio_items')->selectRaw(
            'SUM(' . MoneyAggregate::centsExpression('amount_usd') . ') as net, '
            . 'SUM(' . MoneyAggregate::positiveCentsExpression('amount_usd') . ') as charges, '
            . 'SUM(' . MoneyAggregate::negativeCentsExpression('amount_usd') . ') as credits'
        )->first();

        foreach (['net', 'charges', 'credits'] as $column) {
            $this->assertIsNotFloat($row->{$column}, $column);
        }

        $charges = MoneyAggregate::fromCents($row->charges);
        $credits = MoneyAggregate::fromCents($row->credits);
        $net     = MoneyAggregate::fromCents($row->net);

        $this->assertSame('120.87', $charges);
        $this->assertSame('-33.43', $credits);
        $this->assertSame('87.44', $net);
        $this->assertSame($net, bcadd($charges, $credits, 2));
    }

    private function net(): string
    {
        $value = DB::table('folio_items')
            ->selectRaw('SUM(' . MoneyAggregate::centsExpression('amount_usd') . ') as c')
            ->value('c');

        $this->assertIsNotFloat($value);

        return MoneyAggregate::fromCents($value);
    }

    /** @param list<string> $amounts */
    private function seedAmounts(array $amounts): void
    {
        foreach ($amounts as $amount) {
            FolioItem::factory()->for($this->folio)->create(['amount_usd' => $amount]);
        }
    }
}
