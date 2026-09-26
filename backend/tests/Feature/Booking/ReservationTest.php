<?php

namespace Tests\Feature\Booking;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function makeConfirmedReservation(): Reservation
    {
        $rt = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $r  = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-01', 'check_out' => '2027-03-05']);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);
        return $r;
    }

    public function test_staff_can_list_reservations(): void
    {
        Reservation::factory()->count(3)->create();
        $this->withToken($this->staffToken('reservations.view'))
            ->getJson('/api/cms/reservations')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }

    public function test_staff_can_show_reservation(): void
    {
        $r = $this->makeConfirmedReservation();
        $this->withToken($this->staffToken('reservations.view'))
            ->getJson("/api/cms/reservations/{$r->uuid}")
            ->assertOk()
            ->assertJsonPath('data.booking_code', $r->booking_code);
    }

    public function test_staff_can_confirm_pending_reservation(): void
    {
        $r = Reservation::factory()->create(['status' => ReservationStatus::PENDING]);
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$r->uuid}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::CONFIRMED->value);
    }

    public function test_staff_can_cancel_reservation(): void
    {
        $r = $this->makeConfirmedReservation();
        $this->withToken($this->staffToken('reservations.cancel'))
            ->deleteJson("/api/cms/reservations/{$r->uuid}")
            ->assertStatus(204);

        $this->assertEquals(ReservationStatus::CANCELLED, $r->fresh()->status);
    }

    // Phase 3 D-03: assign-room is pure assignment and never checks in.
    public function test_assign_room_keeps_a_confirmed_reservation_confirmed(): void
    {
        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $r    = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-01', 'check_out' => '2027-03-05']);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->withToken($this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$r->uuid}/assign-room", ['room_uuid' => $room->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::CONFIRMED->value)
            ->assertJsonPath('data.checked_in_at', null)
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid);
    }

    // Phase 3 D-01: the check-in verb is the only way to check a guest in.
    public function test_staff_can_check_in_through_the_check_in_verb(): void
    {
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-03-01 09:00:00'));

        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $r    = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-01', 'check_out' => '2027-03-05']);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->withToken($this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$r->uuid}/check-in", ['room_uuid' => $room->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::CHECKED_IN->value)
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid);
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/cms/reservations')->assertStatus(403);
    }

    public function test_confirm_of_already_confirmed_fails(): void
    {
        $r = $this->makeConfirmedReservation();
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$r->uuid}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state');
    }
}
