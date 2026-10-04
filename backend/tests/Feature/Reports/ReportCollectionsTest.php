<?php

namespace Tests\Feature\Reports;

use App\Models\EventInquiry;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 9 (D-18): completed-payment collections by payable type, separate
 * from revenue; event deposits never in revenue; each payment once.
 */
class ReportCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private const IN_WINDOW  = '2026-10-10 12:00:00';
    private const OUT_WINDOW = '2026-10-05 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-12 09:00:00', 'UTC'));
    }

    private function payment(string $type, int $id, string $amount, string $status = 'completed', string $local = self::IN_WINDOW): void
    {
        DB::table('payments')->insert([
            'uuid'         => (string) Str::uuid(),
            'payable_type' => $type,
            'payable_id'   => $id,
            'method'       => 'cash',
            'amount_usd'   => $amount,
            'recorded_by'  => User::factory()->create()->id,
            'status'       => $status,
            'created_at'   => Carbon::parse($local, 'Asia/Damascus')->utc()->format('Y-m-d H:i:s'),
            'updated_at'   => now(),
        ]);
    }

    private function report(): array
    {
        return app(ReportService::class)->dashboard('2026-10-10', '2026-10-11');
    }

    public function test_collections_split_by_payable_type(): void
    {
        $reservation = Reservation::factory()->create();
        $folio = Folio::factory()->create(['reservation_id' => $reservation->id]);
        $inquiry = EventInquiry::factory()->create();
        $guest = Guest::factory()->create();

        $this->payment(Reservation::class, $reservation->id, '100.00');
        $this->payment(Folio::class, $folio->id, '50.00');
        $this->payment(EventInquiry::class, $inquiry->id, '300.00');
        $this->payment(Guest::class, $guest->id, '10.00');
        $this->payment(Reservation::class, $reservation->id, '999.00', 'pending');
        $this->payment(Reservation::class, $reservation->id, '7.00', 'failed');
        $this->payment(Reservation::class, $reservation->id, '500.00', 'completed', self::OUT_WINDOW);

        $collections = $this->report()['collections'];

        $this->assertSame([
            'basis'              => 'completed_payments',
            'refunds_included'   => false,
            'stays_usd'          => '150.00',
            'event_deposits_usd' => '300.00',
            'other_usd'          => '10.00',
            'total_usd'          => '460.00',
        ], $collections);
    }

    public function test_event_deposit_never_reaches_revenue(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $this->payment(EventInquiry::class, $inquiry->id, '300.00');

        $report = $this->report();
        $this->assertSame('0.00', $report['revenue']['net_usd']);
        $this->assertSame('300.00', $report['collections']['event_deposits_usd']);
    }

    public function test_event_inquiry_morph_class_is_its_fqcn_and_unmapped(): void
    {
        $this->assertSame(EventInquiry::class, (new EventInquiry)->getMorphClass());
        $this->assertNotContains(EventInquiry::class, Relation::morphMap());
        $this->assertNotContains(Reservation::class, Relation::morphMap());
        $this->assertNotContains(Folio::class, Relation::morphMap());
    }

    public function test_a_folio_payment_counts_once_whatever_the_items(): void
    {
        $folio = Folio::factory()->create();
        FolioItem::factory()->count(3)->create(['folio_id' => $folio->id]);
        $this->payment(Folio::class, $folio->id, '75.25');

        $collections = $this->report()['collections'];
        $this->assertSame('75.25', $collections['stays_usd']);
        $this->assertSame('75.25', $collections['total_usd']);
    }

    public function test_payment_factory_rows_are_stays(): void
    {
        $payment = Payment::factory()->create(['amount_usd' => '12.34']);
        DB::table('payments')->where('id', $payment->id)->update([
            'created_at' => Carbon::parse(self::IN_WINDOW, 'Asia/Damascus')->utc()->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame('12.34', $this->report()['collections']['stays_usd']);
    }

    public function test_empty_is_zero(): void
    {
        $collections = $this->report()['collections'];

        foreach (['stays_usd', 'event_deposits_usd', 'other_usd', 'total_usd'] as $key) {
            $this->assertSame('0.00', $collections[$key], $key);
        }
    }
}
