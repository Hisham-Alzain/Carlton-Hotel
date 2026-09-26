<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\CheckAvailabilityAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The shared D-04 predicate and picker on CheckAvailabilityAction
 * (isRoomFree / freeRoomsFor / pickRoomFor), and the booking picker
 * findFreeRoom kept habitability-blind.
 */
class RoomAvailabilityPredicateTest extends TestCase
{
    use RefreshDatabase;

    private CheckAvailabilityAction $availability;
    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->availability = app(CheckAvailabilityAction::class);
        $this->type         = RoomType::factory()->create();
    }

    private function room(string $number, string $status = 'available'): Room
    {
        return Room::factory()->create(['room_type_id' => $this->type->id, 'number' => $number, 'status' => $status]);
    }

    private function stay(?Room $room, string $state, string $in = '2027-04-01', string $out = '2027-04-04'): Reservation
    {
        $factory     = $state === 'pending' ? Reservation::factory() : Reservation::factory()->{$state}();
        $reservation = $factory->create(['check_in' => $in, 'check_out' => $out]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $this->type->id,
            'room_id'        => $room?->id,
        ]);
        return $reservation;
    }

    public function test_is_room_free_ignores_the_reservations_own_hold(): void
    {
        $room = $this->room('101');
        $mine = $this->stay($room, 'confirmed');

        $this->assertTrue($this->availability->isRoomFree($room, $mine));
    }

    public function test_is_room_free_is_false_when_another_holding_stay_overlaps(): void
    {
        $room  = $this->room('101');
        $mine  = $this->stay(null, 'confirmed');
        $other = $this->stay($room, 'pending', '2027-04-03', '2027-04-06');

        $this->assertFalse($this->availability->isRoomFree($room, $mine), 'pending holds the room');

        $other->update(['status' => ReservationStatus::CANCELLED]);
        $this->assertTrue($this->availability->isRoomFree($room, $mine), 'cancelled releases it');

        $other->update(['status' => ReservationStatus::PENDING_VERIFICATION, 'hold_expires_at' => now()->subMinute()]);
        $this->assertTrue($this->availability->isRoomFree($room, $mine), 'expired hold releases it');

        $other->update(['status' => ReservationStatus::PENDING_VERIFICATION, 'hold_expires_at' => now()->addMinutes(5)]);
        $this->assertFalse($this->availability->isRoomFree($room, $mine), 'live hold keeps it');
    }

    public function test_is_room_free_is_true_for_back_to_back_stays(): void
    {
        $room = $this->room('101');
        $mine = $this->stay(null, 'confirmed');
        $this->stay($room, 'checkedIn', '2027-03-28', '2027-04-01');
        $this->stay($room, 'confirmed', '2027-04-04', '2027-04-07');

        $this->assertTrue($this->availability->isRoomFree($room, $mine));
    }

    public function test_free_rooms_for_excludes_maintenance_and_rooms_named_by_other_holds_in_number_order(): void
    {
        $r103 = $this->room('103', 'dirty');
        $r101 = $this->room('101');
        $this->room('102', 'maintenance');
        $r104 = $this->room('104');
        Room::factory()->inactive()->create(['room_type_id' => $this->type->id, 'number' => '100']);
        $mine = $this->stay($r101, 'confirmed');
        $this->stay($r104, 'confirmed', '2027-04-02', '2027-04-03');

        $free = $this->availability->freeRoomsFor($mine, $this->type->id);

        $this->assertSame(['101', '103'], $free->pluck('number')->all(), 'own room kept; maintenance, held and inactive dropped');
        $this->assertTrue($free->first()->is($r101));
        $this->assertTrue($free->last()->is($r103));
    }

    public function test_pick_room_prefers_available_then_lowest_number(): void
    {
        $r101 = $this->room('101', 'dirty');
        $r102 = $this->room('102');
        $r103 = $this->room('103');
        $mine = $this->stay(null, 'confirmed');

        $this->assertTrue($this->availability->pickRoomFor($mine, $this->type->id)->is($r102));

        $r102->update(['status' => 'dirty']);
        $r103->update(['status' => 'dirty']);
        $this->assertTrue($this->availability->pickRoomFor($mine, $this->type->id)->is($r101), 'all dirty: lowest number');

        foreach ([$r101, $r102, $r103] as $room) {
            $room->update(['status' => 'maintenance']);
        }
        $this->assertNull($this->availability->pickRoomFor($mine, $this->type->id), 'maintenance is never picked');
    }

    public function test_pick_room_returns_null_when_null_room_holds_exhaust_capacity(): void
    {
        $this->room('101');
        $this->room('102');
        $mine = $this->stay(null, 'confirmed');
        $this->stay(null, 'confirmed');
        $this->stay(null, 'pending', '2027-04-02', '2027-04-05');

        $this->assertNull($this->availability->pickRoomFor($mine, $this->type->id));
        $this->assertCount(0, $this->availability->freeRoomsFor($mine, $this->type->id));
    }

    public function test_find_free_room_keeps_booking_behaviour(): void
    {
        $r101 = $this->room('101', 'dirty');
        $this->room('102');

        // Booking is habitability-blind: the housekeeping state of today is irrelevant.
        $room = $this->availability->findFreeRoom($this->type->id, '2027-04-01', '2027-04-04');
        $this->assertTrue($room->is($r101));

        $this->stay($r101, 'confirmed');
        $this->assertSame('102', $this->availability->findFreeRoom($this->type->id, '2027-04-01', '2027-04-04')->number);
        $this->assertTrue($this->availability->handle($this->type->id, '2027-04-01', '2027-04-04'));
        $this->assertSame(1, $this->availability->availableCount($this->type->id, '2027-04-01', '2027-04-04'));
    }
}
