<?php

namespace Tests\Feature\Reports;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\User;
use App\Services\Reports\ReportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9 (D-17): booked room-nights over a half-open period, capacity and
 * arrivals/departures, computed with portable SQL.
 */
class ReportOccupancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2026-10-10 09:00:00', 'UTC'));
    }

    private function seedCapacity(): void
    {
        Room::factory()->count(3)->create();
        Room::factory()->create(['status' => 'maintenance']);   // active: counts
        Room::factory()->inactive()->create();                   // excluded
        Room::factory()->create()->delete();                     // trashed: excluded
    }

    private function stay(string $in, string $out, int $lines = 1, ReservationStatus $status = ReservationStatus::CONFIRMED, bool $assignFirst = false): Reservation
    {
        $reservation = Reservation::factory()->create(['status' => $status, 'check_in' => $in, 'check_out' => $out]);
        for ($i = 0; $i < $lines; $i++) {
            ReservationRoom::factory()->create([
                'reservation_id' => $reservation->id,
                'room_id'        => $assignFirst && $i === 0 ? Room::factory()->inactive()->create()->id : null,
            ]);
        }

        return $reservation;
    }

    private function report(string $from, string $to): array
    {
        return app(ReportService::class)->dashboard($from, $to);
    }

    public function test_two_lines_over_two_nights(): void
    {
        $this->seedCapacity();
        $this->stay('2026-10-08', '2026-10-12', 2, assignFirst: true);

        $occupancy = $this->report('2026-10-10', '2026-10-11')['occupancy'];

        $this->assertSame(4, $occupancy['occupied_room_nights']);
        $this->assertSame(8, $occupancy['available_room_nights']);
        $this->assertSame('0.5000', $occupancy['occupancy_rate']);
    }

    public function test_half_open_edges(): void
    {
        $this->seedCapacity();
        $this->stay('2026-10-07', '2026-10-10');        // ends on date_from → 0
        $this->stay('2026-10-11', '2026-10-14');        // starts on date_to → 1
        $this->stay('2026-10-12', '2026-10-13');        // starts after date_to → 0
        $this->stay('2026-10-09', '2026-10-11');        // night of 10th only → 1

        $this->assertSame(2, $this->report('2026-10-10', '2026-10-11')['occupancy']['occupied_room_nights']);
    }

    public function test_only_confirmed_checked_in_and_checked_out_count(): void
    {
        $this->seedCapacity();
        $this->stay('2026-10-10', '2026-10-11', 1, ReservationStatus::CANCELLED);
        $this->stay('2026-10-10', '2026-10-11', 1, ReservationStatus::PENDING);
        $this->stay('2026-10-10', '2026-10-11', 1, ReservationStatus::PENDING_VERIFICATION);
        $this->stay('2026-10-10', '2026-10-11', 1, ReservationStatus::CHECKED_IN);
        $this->stay('2026-10-01', '2026-10-03', 1, ReservationStatus::CHECKED_OUT);

        $this->assertSame(1, $this->report('2026-10-10', '2026-10-10')['occupancy']['occupied_room_nights']);
        $this->assertSame(2, $this->report('2026-10-01', '2026-10-02')['occupancy']['occupied_room_nights']);
    }

    public function test_no_rooms_and_overbooking(): void
    {
        $this->assertSame(
            ['occupied_room_nights' => 0, 'available_room_nights' => 0, 'occupancy_rate' => '0.0000'],
            $this->report('2026-10-10', '2026-10-10')['occupancy'],
        );

        Room::factory()->create();
        $this->stay('2026-10-10', '2026-10-11', 3);

        $this->assertSame('3.0000', $this->report('2026-10-10', '2026-10-10')['occupancy']['occupancy_rate']);
    }

    public function test_datetime_storage_gives_the_same_numbers(): void
    {
        $this->seedCapacity();
        $a = $this->stay('2026-10-08', '2026-10-12', 2);
        $b = $this->stay('2026-10-11', '2026-10-13', 1);
        $before = $this->report('2026-10-10', '2026-10-11');

        foreach ([$a, $b] as $r) {
            DB::table('reservations')->where('id', $r->id)->update([
                'check_in'  => $r->check_in->toDateString() . ' 00:00:00',
                'check_out' => $r->check_out->toDateString() . ' 00:00:00',
            ]);
        }
        $after = $this->report('2026-10-10', '2026-10-11');

        $this->assertSame($before['occupancy'], $after['occupancy']);
        $this->assertSame($before['arrivals'], $after['arrivals']);
        $this->assertSame($before['departures'], $after['departures']);
        $this->assertSame(5, $after['occupancy']['occupied_room_nights']);
    }

    public function test_arrivals_and_departures_count_reservations_once(): void
    {
        $this->stay('2026-10-10', '2026-10-15', 3);                                // arrival in period
        $this->stay('2026-10-05', '2026-10-11', 2);                                // departure on date_to
        $this->stay('2026-10-10', '2026-10-11', 1, ReservationStatus::CANCELLED);  // excluded
        $this->stay('2026-10-09', '2026-10-12');                                   // neither
        // QA regression: arrival in period, departure on date_to + 1 (half-open upper bound),
        // stored as a plain MySQL-style DATE so an inclusive `<= date_to + 1` bound would count it.
        $edge = $this->stay('2026-10-11', '2026-10-12');
        DB::table('reservations')->where('id', $edge->id)->update(['check_in' => '2026-10-11', 'check_out' => '2026-10-12']);

        $report = $this->report('2026-10-10', '2026-10-11');

        $this->assertSame(2, $report['arrivals']);
        $this->assertSame(1, $report['departures']);
    }

    public function test_no_driver_specific_date_sql(): void
    {
        $this->seedCapacity();
        $this->stay('2026-10-08', '2026-10-12', 2);

        $sql = [];
        DB::listen(function ($event) use (&$sql): void {
            $sql[] = $event->sql;
        });

        $user = User::factory()->create();
        $this->seed(RolesAndPermissionsSeeder::class);
        $user->givePermissionTo('reports.view');
        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/reports/dashboard?date_from=2026-10-01&date_to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.occupancy.occupied_room_nights', 8)
            ->assertJsonPath('data.occupancy.available_room_nights', 124);

        foreach ($sql as $statement) {
            $this->assertDoesNotMatchRegularExpression('/julianday|datediff|\bdate\(/i', $statement);
        }
    }
}
