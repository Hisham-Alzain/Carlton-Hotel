<?php

namespace Tests\Feature\Booking;

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
        $r = Reservation::factory()->create(['status' => Reservation::STATUS_PENDING]);
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$r->uuid}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Reservation::STATUS_CONFIRMED);
    }

    public function test_staff_can_cancel_reservation(): void
    {
        $r = $this->makeConfirmedReservation();
        $this->withToken($this->staffToken('reservations.cancel'))
            ->deleteJson("/api/cms/reservations/{$r->uuid}")
            ->assertStatus(204);

        $this->assertEquals(Reservation::STATUS_CANCELLED, $r->fresh()->status);
    }

    public function test_staff_can_assign_room_at_checkin(): void
    {
        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $r    = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-01', 'check_out' => '2027-03-05']);
        ReservationRoom::factory()->create(['reservation_id' => $r->id, 'room_type_id' => $rt->id]);

        $this->withToken($this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$r->uuid}/assign-room", ['room_uuid' => $room->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', Reservation::STATUS_CHECKED_IN);
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
