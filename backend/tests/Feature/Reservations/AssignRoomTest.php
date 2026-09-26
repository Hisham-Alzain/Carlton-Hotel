<?php

namespace Tests\Feature\Reservations;

use App\Actions\Booking\AssignRoomAction;
use App\Enums\ReservationStatus;
use App\Events\GuestCheckedIn;
use App\Events\RoomAssigned;
use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * POST /api/cms/reservations/{reservation}/assign-room, narrowed to pure
 * assignment (Phase 3, RESV-03, D-03, D-05). Breaking change: it no longer
 * checks the guest in.
 */
class AssignRoomTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->type = RoomType::factory()->create();
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function room(string $number, string $status = 'available', ?RoomType $type = null): Room
    {
        return Room::factory()->create(['room_type_id' => ($type ?? $this->type)->id, 'number' => $number, 'status' => $status]);
    }

    private function stay(string $state, ?Room $room = null, string $in = '2027-03-10', string $out = '2027-03-12', array $attrs = []): Reservation
    {
        $factory     = $state === 'pending' ? Reservation::factory() : Reservation::factory()->{$state}();
        $reservation = $factory->create(array_merge(['check_in' => $in, 'check_out' => $out], $attrs));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $this->type->id,
            'room_id'        => $room?->id,
        ]);
        return $reservation;
    }

    private function assign(Reservation $reservation, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('reservations.create'))
            ->postJson("/api/cms/reservations/{$reservation->uuid}/assign-room", $body);
    }

    public function test_pre_arrival_assignment_keeps_the_reservation_confirmed(): void
    {
        $room        = $this->room('101');
        $reservation = $this->stay('confirmed', null, '2027-03-20', '2027-03-22');

        $this->assign($reservation, ['room_uuid' => $room->uuid])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.checked_in_at', null)
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid);

        $fresh = $reservation->fresh();
        $this->assertSame(ReservationStatus::CONFIRMED, $fresh->status);
        $this->assertNull($fresh->checked_in_at);
        $this->assertSame($room->id, $fresh->rooms()->first()->room_id);
    }

    public function test_pre_arrival_assignment_dispatches_no_room_assigned(): void
    {
        Event::fake([RoomAssigned::class, GuestCheckedIn::class]);
        $room        = $this->room('101');
        $reservation = $this->stay('confirmed');

        $this->assign($reservation, ['room_uuid' => $room->uuid])->assertOk();

        Event::assertNotDispatched(RoomAssigned::class);
        Event::assertNotDispatched(GuestCheckedIn::class);
    }

    public function test_room_move_during_a_stay_fires_room_assigned_once(): void
    {
        Event::fake([RoomAssigned::class, GuestCheckedIn::class]);
        $from        = $this->room('101');
        $to          = $this->room('102');
        $reservation = $this->stay('checkedIn', $from, attrs: ['checked_in_at' => '2027-03-10 07:15:00']);

        $this->assign($reservation, ['room_uuid' => $to->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.checked_in_at', '2027-03-10T07:15:00+00:00')
            ->assertJsonPath('data.rooms.0.room_uuid', $to->uuid);

        $this->assertSame($to->id, $reservation->rooms()->first()->room_id);
        Event::assertDispatchedTimes(RoomAssigned::class, 1);
        Event::assertDispatched(RoomAssigned::class, fn (RoomAssigned $e) => $e->reservation->uuid === $reservation->uuid);
        Event::assertNotDispatched(GuestCheckedIn::class);
    }

    public function test_reassigning_the_same_room_is_a_no_op(): void
    {
        Event::fake([RoomAssigned::class]);
        $room        = $this->room('101');
        $reservation = $this->stay('checkedIn', $room, attrs: ['checked_in_at' => '2027-03-10 07:15:00']);

        $this->assign($reservation, ['room_uuid' => $room->uuid])
            ->assertOk()
            ->assertJsonPath('data.rooms.0.room_uuid', $room->uuid);

        // Omitting room_uuid re-validates the reserved room: also a no-op.
        $this->assign($reservation, [])->assertOk()->assertJsonPath('data.status', 'checked_in');

        Event::assertNotDispatched(RoomAssigned::class);
    }

    public static function refusedStatuses(): array
    {
        return [
            'pending_verification' => ['pending_verification'],
            'pending'              => ['pending'],
            'checked_out'          => ['checked_out'],
            'cancelled'            => ['cancelled'],
        ];
    }

    #[DataProvider('refusedStatuses')]
    public function test_other_statuses_are_refused(string $status): void
    {
        $current     = $this->room('101');
        $target      = $this->room('102');
        $reservation = $this->stay('confirmed', $current);
        $reservation->update([
            'status'          => $status,
            'hold_expires_at' => $status === 'pending_verification' ? now()->addMinutes(5) : null,
        ]);

        $this->assign($reservation, ['room_uuid' => $target->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state')
            ->assertJsonPath('context.status', $status)
            ->assertJsonPath('context.allowed', ['confirmed', 'checked_in']);

        $this->assertSame($current->id, $reservation->rooms()->first()->room_id);
    }

    public function test_maintenance_room_is_refused(): void
    {
        $maintenance = $this->room('109', 'maintenance');

        foreach (['confirmed', 'checkedIn'] as $i => $state) {
            $current     = $this->room((string) (101 + $i));
            $reservation = $this->stay($state, $current);

            $this->assign($reservation, ['room_uuid' => $maintenance->uuid])
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'room_out_of_order')
                ->assertJsonPath('context.room_uuid', $maintenance->uuid)
                ->assertJsonPath('context.housekeeping_status', 'maintenance');

            $this->assertSame($current->id, $reservation->rooms()->first()->room_id, $state);
        }
    }

    public function test_room_held_by_an_overlapping_stay_is_refused(): void
    {
        $room        = $this->room('101');
        $reservation = $this->stay('confirmed', null, '2027-03-20', '2027-03-23');
        $this->stay('pending', $room, '2027-03-22', '2027-03-25');

        $this->assign($reservation, ['room_uuid' => $room->uuid])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'room_already_assigned');
        $this->assertNull($reservation->rooms()->first()->room_id);

        // A back-to-back stay on the same room does not block.
        $room2 = $this->room('102');
        $this->stay('confirmed', $room2, '2027-03-23', '2027-03-26');
        $this->stay('checkedOut', $room2, '2027-03-17', '2027-03-20');

        $this->assign($reservation, ['room_uuid' => $room2->uuid])
            ->assertOk()
            ->assertJsonPath('data.rooms.0.room_uuid', $room2->uuid);
    }

    public function test_room_of_another_type_is_refused(): void
    {
        $foreign     = $this->room('301', 'available', RoomType::factory()->create());
        $reservation = $this->stay('confirmed');

        $this->assign($reservation, ['room_uuid' => $foreign->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state');

        $this->assertNull($reservation->rooms()->first()->room_id);
    }

    public function test_no_room_given_and_none_reserved_is_refused(): void
    {
        $this->room('101');
        $reservation = $this->stay('confirmed');

        $this->assign($reservation, [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state');

        $bare = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-10', 'check_out' => '2027-03-12']);
        $this->assign($bare, ['room_uuid' => $this->room('102')->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'reservation_state');
    }

    public function test_assign_room_never_touches_housekeeping_status(): void
    {
        $dirty       = $this->room('101', 'dirty');
        $available   = $this->room('102');
        $reservation = $this->stay('checkedIn', $available);

        $this->assign($reservation, ['room_uuid' => $dirty->uuid])->assertOk();

        $this->assertSame('dirty', $dirty->fresh()->status->value);
        $this->assertSame('available', $available->fresh()->status->value);
        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public function test_invalid_room_uuid_is_rejected(): void
    {
        $reservation = $this->stay('confirmed');

        $this->assign($reservation, ['room_uuid' => 'not-a-room'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['room_uuid']);
    }

    public function test_a_stale_model_is_re_read_under_lock(): void
    {
        $room  = $this->room('101');
        $stale = $this->stay('confirmed');
        Reservation::find($stale->id)->update(['status' => ReservationStatus::CHECKED_OUT]);

        $this->assertSame(ReservationStatus::CONFIRMED, $stale->status);

        try {
            app(AssignRoomAction::class)->handle($stale, $room);
            $this->fail('ReservationStateException expected');
        } catch (ReservationStateException $e) {
            $this->assertSame('checked_out', $e->context()['status']);
        }

        $this->assertNull($stale->rooms()->first()->room_id);
    }

    public function test_requires_reservations_create(): void
    {
        $room        = $this->room('101');
        $reservation = $this->stay('confirmed');

        foreach ([$this->staffToken('reservations.view'), $this->presetToken('housekeeping')] as $token) {
            $this->assign($reservation, ['room_uuid' => $room->uuid], $token)
                ->assertForbidden()
                ->assertJsonPath('error_code', 'forbidden');
        }

        $this->assertNull($reservation->rooms()->first()->room_id);
    }

    public function test_requires_authentication(): void
    {
        $reservation = $this->stay('confirmed');

        $this->postJson("/api/cms/reservations/{$reservation->uuid}/assign-room", [])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');
    }
}
