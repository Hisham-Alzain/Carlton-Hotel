<?php

namespace Tests\Feature\Reports;

use App\Enums\ReservationStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CountsDomainQueries;
use Tests\TestCase;

/**
 * Phase 9 (D-22): the report's statement count does not depend on data
 * volume or period length.
 */
class ReportQueryBudgetTest extends TestCase
{
    use CountsDomainQueries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00', 'UTC'));
    }

    public function test_budget_is_volume_and_period_invariant(): void
    {
        $service = app(ReportService::class);

        $empty = $this->countDomainQueries(fn () => $service->dashboard('2026-10-10', '2026-10-10'));

        Room::factory()->count(10)->create();
        for ($i = 0; $i < 30; $i++) {
            $in = sprintf('2026-10-%02d', 1 + $i % 25);
            $reservation = Reservation::factory()->create([
                'status' => ReservationStatus::CONFIRMED,
                'check_in' => $in,
                'check_out' => Carbon::parse($in)->addDays(1 + $i % 4)->toDateString(),
            ]);
            ReservationRoom::factory()->count(2)->create(['reservation_id' => $reservation->id]);
        }
        $folio = Folio::factory()->create();
        FolioItem::factory()->count(200)->create(['folio_id' => $folio->id]);
        Payment::factory()->count(50)->create();
        ServiceRequest::factory()->count(40)->create();
        Ticket::factory()->count(40)->create();

        $full = $this->countDomainQueries(fn () => $service->dashboard('2026-10-01', '2026-10-31'));

        // capacity COUNT; room-night pairs GROUP BY; arrivals/departures in one
        // conditional aggregate; folio lines by source; payments by payable
        // type; service-request statuses; ticket statuses = 7 (D-22 ≤ 8).
        $this->assertSame($empty, $full);
        $this->assertSame(7, $full);
    }
}
