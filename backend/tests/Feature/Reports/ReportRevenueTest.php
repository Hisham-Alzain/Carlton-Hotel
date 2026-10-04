<?php

namespace Tests\Feature\Reports;

use App\Models\Folio;
use App\Models\FolioItem;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9 (D-05, D-18, D-20, D-21): posted folio-line revenue over the
 * hotel-local period's UTC window.
 */
class ReportRevenueTest extends TestCase
{
    use RefreshDatabase;

    private Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-12 09:00:00', 'UTC'));
        $this->folio = Folio::factory()->create();
    }

    /** Post a line at a hotel-local wall-clock time. */
    private function line(string $amount, string $source, string $localTime = '2026-10-10 12:00:00'): void
    {
        $item = FolioItem::factory()->create([
            'folio_id' => $this->folio->id, 'amount_usd' => $amount, 'source_type' => $source,
        ]);
        DB::table('folio_items')->where('id', $item->id)->update([
            'created_at' => Carbon::parse($localTime, 'Asia/Damascus')->utc()->format('Y-m-d H:i:s'),
        ]);
    }

    private function revenue(string $from = '2026-10-10', string $to = '2026-10-11'): array
    {
        return app(ReportService::class)->dashboard($from, $to)['revenue'];
    }

    public function test_charges_credits_net_and_by_source(): void
    {
        $this->line('120.00', 'reservation');
        $this->line('15.50', 'service_request');
        $this->line('-20.00', 'credit');
        foreach (range(1, 10) as $i) {
            $this->line('0.10', 'manual');
        }

        $revenue = $this->revenue();

        $this->assertSame('posted_folio_lines', $revenue['basis']);
        $this->assertSame('USD', $revenue['currency']);
        $this->assertSame('136.50', $revenue['charges_usd']);
        $this->assertSame('-20.00', $revenue['credits_usd']);
        $this->assertSame('116.50', $revenue['net_usd']);
        $this->assertSame([
            'reservation'     => '120.00',
            'service_booking' => '0.00',
            'service_request' => '15.50',
            'manual'          => '1.00',
            'credit'          => '-20.00',
        ], $revenue['by_source']);
        $this->assertSame($revenue['net_usd'], bcadd(bcadd(bcadd($revenue['by_source']['reservation'], $revenue['by_source']['service_request'], 2), $revenue['by_source']['manual'], 2), $revenue['by_source']['credit'], 2));
    }

    public function test_window_edges_follow_the_hotel_day(): void
    {
        $this->line('1.00', 'manual', '2026-10-11 23:59:59');   // last second of date_to: in
        $this->line('2.00', 'manual', '2026-10-12 00:00:00');   // next local day: out
        $this->line('4.00', 'manual', '2026-10-09 23:59:59');   // day before date_from: out
        $this->line('8.00', 'manual', '2026-10-10 00:00:00');   // first second of date_from: in

        $this->assertSame('9.00', $this->revenue()['net_usd']);
        $this->assertSame('9.00', $this->revenue()['by_source']['manual']);
    }

    public function test_empty_period_is_all_zero_strings(): void
    {
        $revenue = $this->revenue();

        foreach (['charges_usd', 'credits_usd', 'net_usd'] as $key) {
            $this->assertSame('0.00', $revenue[$key], $key);
        }
        $this->assertSame(
            ['reservation', 'service_booking', 'service_request', 'manual', 'credit'],
            array_keys($revenue['by_source']),
        );
        foreach ($revenue['by_source'] as $source => $value) {
            $this->assertSame('0.00', $value, $source);
        }
    }

    public function test_by_source_values_are_strings_never_floats(): void
    {
        $this->line('0.29', 'service_booking');
        $this->line('0.57', 'service_booking');

        $revenue = $this->revenue();
        $this->assertSame('0.86', $revenue['by_source']['service_booking']);
        array_walk_recursive($revenue, fn ($v) => $this->assertIsNotFloat($v));
    }
}
