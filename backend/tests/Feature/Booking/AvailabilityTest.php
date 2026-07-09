<?php

namespace Tests\Feature\Booking;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeRoomType(): RoomType
    {
        $rt = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        return $rt;
    }

    public function test_available_when_no_reservations(): void
    {
        $rt = $this->makeRoomType();
        $res = $this->getJson("/api/public/availability?room_type_uuid={$rt->uuid}&check_in=2027-01-01&check_out=2027-01-03");
        $res->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.rooms_available', 1);
    }

    public function test_not_available_when_all_rooms_booked(): void
    {
        $rt = $this->makeRoomType();
        $r  = Reservation::factory()->confirmed()->create(['check_in' => '2027-01-01', 'check_out' => '2027-01-05']);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->getJson("/api/public/availability?room_type_uuid={$rt->uuid}&check_in=2027-01-02&check_out=2027-01-04")
            ->assertOk()->assertJsonPath('data.available', false);
    }

    public function test_adjacent_dates_do_not_block(): void
    {
        $rt = $this->makeRoomType();
        // Existing reservation: Jan 1–3. New request: Jan 3–5 (check_out = check_in → no overlap)
        $r = Reservation::factory()->confirmed()->create(['check_in' => '2027-01-01', 'check_out' => '2027-01-03']);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->getJson("/api/public/availability?room_type_uuid={$rt->uuid}&check_in=2027-01-03&check_out=2027-01-05")
            ->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_live_soft_hold_counts_as_occupied(): void
    {
        $rt = $this->makeRoomType();
        $r  = Reservation::factory()->pendingVerification()->create([
            'check_in' => '2027-01-01', 'check_out' => '2027-01-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->getJson("/api/public/availability?room_type_uuid={$rt->uuid}&check_in=2027-01-02&check_out=2027-01-04")
            ->assertOk()->assertJsonPath('data.available', false);
    }

    public function test_expired_soft_hold_does_not_block(): void
    {
        $rt = $this->makeRoomType();
        $r  = Reservation::factory()->expiredHold()->create([
            'check_in' => '2027-01-01', 'check_out' => '2027-01-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->getJson("/api/public/availability?room_type_uuid={$rt->uuid}&check_in=2027-01-02&check_out=2027-01-04")
            ->assertOk()->assertJsonPath('data.available', true);
    }

    public function test_cancelled_reservation_does_not_block(): void
    {
        $rt = $this->makeRoomType();
        $r  = Reservation::factory()->cancelled()->create([
            'check_in' => '2027-01-01', 'check_out' => '2027-01-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->getJson("/api/public/availability?room_type_uuid={$rt->uuid}&check_in=2027-01-02&check_out=2027-01-04")
            ->assertOk()->assertJsonPath('data.available', true);
    }
}
