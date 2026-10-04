<?php

namespace Tests\Feature\Reports;

use App\Enums\ServiceRequestStatus;
use App\Enums\TicketStatus;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Reports\ReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 9 (D-19, D-20): current open work, independent of the period; no
 * fabricated metrics anywhere in the response.
 */
class ReportOpenWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-12 09:00:00', 'UTC'));
    }

    private function report(string $from = '2026-10-10', string $to = '2026-10-11'): array
    {
        return app(ReportService::class)->dashboard($from, $to);
    }

    public function test_counts_active_requests_and_tickets(): void
    {
        $this->travelTo(Carbon::parse('2025-01-01 09:00:00', 'UTC'));
        ServiceRequest::factory()->count(2)->create(['status' => ServiceRequestStatus::NEW]);
        ServiceRequest::factory()->create(['status' => ServiceRequestStatus::IN_PROGRESS]);
        ServiceRequest::factory()->count(4)->create(['status' => ServiceRequestStatus::COMPLETED]);
        ServiceRequest::factory()->create(['status' => ServiceRequestStatus::CANCELLED]);
        foreach (TicketStatus::cases() as $status) {
            Ticket::factory()->create(['status' => $status]);
        }
        $this->travelTo(Carbon::parse('2026-10-12 09:00:00', 'UTC'));

        $report = $this->report();
        $work = $report['open_work'];

        $this->assertSame('current_state', $work['basis']);
        $this->assertSame($report['generated_at'], $work['as_of']);
        $this->assertSame(['new' => 2, 'in_progress' => 1, 'total' => 3], $work['service_requests']);
        $this->assertSame(['open' => 1, 'assigned' => 1, 'in_progress' => 1, 'waiting_guest' => 1, 'total' => 4], $work['tickets']);

        // Period-independent.
        $this->assertSame($work['service_requests'], $this->report('2026-01-01', '2026-01-01')['open_work']['service_requests']);
        $this->assertSame($work['tickets'], $this->report('2027-03-01', '2027-03-31')['open_work']['tickets']);
    }

    public function test_empty_has_every_key_at_zero(): void
    {
        $work = $this->report()['open_work'];

        $this->assertSame(['new' => 0, 'in_progress' => 0, 'total' => 0], $work['service_requests']);
        $this->assertSame(['open' => 0, 'assigned' => 0, 'in_progress' => 0, 'waiting_guest' => 0, 'total' => 0], $work['tickets']);
    }

    public function test_response_has_exactly_the_documented_blocks_and_no_fabricated_metrics(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('reports.view');

        $data = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/reports/dashboard')->assertOk()->json('data');

        $this->assertSame(
            ['period', 'generated_at', 'occupancy', 'arrivals', 'departures', 'revenue', 'collections', 'open_work'],
            array_keys($data),
        );

        $keys = [];
        $walk = function (array $node) use (&$walk, &$keys): void {
            foreach ($node as $key => $value) {
                $keys[] = strtolower((string) $key);
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        foreach (['adr', 'revpar', 'mtd', 'ytd', 'kpis', 'revenue_today', 'daily', 'room_types'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, $forbidden);
        }
    }
}
