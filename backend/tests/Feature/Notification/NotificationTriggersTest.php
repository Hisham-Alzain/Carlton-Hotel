<?php

namespace Tests\Feature\Notification;

use App\Contracts\FirebaseServiceInterface;
use App\Enums\NotificationType;
use App\Models\DeviceToken;
use App\Models\EventInquiry;
use App\Models\GuestNotification;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * @group p9
 */
class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    public function test_checking_in_pushes_room_ready_to_the_guest(): void
    {
        // Phase 3 D-05: the room-ready push fires at check-in (GuestCheckedIn).
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-03-01 09:00:00'));
        $fake = $this->fakeFirebase();

        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $reservation = Reservation::factory()->confirmed()->create([
            'check_in' => '2027-03-01', 'check_out' => '2027-03-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $rt->id]);
        DeviceToken::factory()->create(['guest_id' => $reservation->guest_id, 'token' => 'guest-tok']);

        $staff = User::factory()->create();
        $staff->givePermissionTo('reservations.create');

        $this->withToken($staff->createToken('t')->plainTextToken)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-in", ['room_uuid' => $room->uuid])
            ->assertOk();

        $this->assertDatabaseHas('guest_notifications', [
            'guest_id' => $reservation->guest_id,
            'type'     => NotificationType::ROOM_READY->value,
        ]);
        $this->assertCount(1, $fake->pushes);
        $this->assertEquals(['guest-tok'], $fake->pushes[0]['tokens']);
    }

    public function test_checking_in_with_no_device_token_still_records_notification_without_push(): void
    {
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-03-01 09:00:00'));
        $fake = $this->fakeFirebase();

        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $reservation = Reservation::factory()->confirmed()->create([
            'check_in' => '2027-03-01', 'check_out' => '2027-03-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $rt->id]);

        $staff = User::factory()->create();
        $staff->givePermissionTo('reservations.create');

        $this->withToken($staff->createToken('t')->plainTextToken)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-in", ['room_uuid' => $room->uuid])
            ->assertOk();

        $this->assertDatabaseHas('guest_notifications', ['type' => NotificationType::ROOM_READY->value]);
        $this->assertCount(0, $fake->pushes);
    }

    public function test_moving_a_checked_in_guest_pushes_room_ready(): void
    {
        // Phase 3 D-05: a room move during the stay (RoomAssigned) pushes again.
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-03-01 09:00:00'));
        $fake = $this->fakeFirebase();

        $rt    = RoomType::factory()->create(['base_price_usd' => 100]);
        $roomA = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true, 'number' => '401']);
        $roomB = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true, 'number' => '402']);
        $reservation = Reservation::factory()->confirmed()->create([
            'check_in' => '2027-03-01', 'check_out' => '2027-03-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $rt->id]);
        DeviceToken::factory()->create(['guest_id' => $reservation->guest_id, 'token' => 'guest-tok']);

        $staff = User::factory()->create();
        $staff->givePermissionTo('reservations.create');
        $token = $staff->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/check-in", ['room_uuid' => $roomA->uuid])
            ->assertOk();
        $this->withToken($token)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/assign-room", ['room_uuid' => $roomB->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in');

        $this->assertSame(2, GuestNotification::where('guest_id', $reservation->guest_id)
            ->where('type', NotificationType::ROOM_READY->value)->count());
        $this->assertCount(2, $fake->pushes);
    }

    public function test_pre_arrival_assignment_sends_no_room_ready(): void
    {
        // Phase 3 D-03/D-05: assigning a room to a confirmed booking is silent.
        $fake = $this->fakeFirebase();

        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        $room = Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $reservation = Reservation::factory()->confirmed()->create([
            'check_in' => '2027-03-01', 'check_out' => '2027-03-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $reservation->id, 'room_type_id' => $rt->id]);
        DeviceToken::factory()->create(['guest_id' => $reservation->guest_id, 'token' => 'guest-tok']);

        $staff = User::factory()->create();
        $staff->givePermissionTo('reservations.create');

        $this->withToken($staff->createToken('t')->plainTextToken)
            ->postJson("/api/cms/reservations/{$reservation->uuid}/assign-room", ['room_uuid' => $room->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseMissing('guest_notifications', ['type' => NotificationType::ROOM_READY->value]);
        $this->assertCount(0, $fake->pushes);
    }

    public function test_public_inquiry_submission_routes_a_notification_to_the_department(): void
    {
        $this->fakeFirebase();

        $this->postJson('/api/event-inquiries', [
            'name' => 'Jane Doe', 'email' => 'jane@example.com',
            'event_type' => 'wedding', 'requirements' => [],
        ])->assertCreated();

        $inquiry = EventInquiry::first();

        $this->assertDatabaseHas('guest_notifications', [
            'department' => $inquiry->department,
            'type'       => NotificationType::INQUIRY_ROUTED->value,
        ]);
        $notification = GuestNotification::where('type', NotificationType::INQUIRY_ROUTED->value)->first();
        $this->assertNull($notification->guest_id);
        $this->assertNotNull($notification->sent_at);
    }
}
