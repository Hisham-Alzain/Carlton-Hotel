<?php

namespace Tests\Unit\Booking;

use App\Actions\Booking\CheckInReservationAction;
use App\Enums\ReservationStatus;
use App\Events\GuestCheckedIn;
use App\Exceptions\NoAvailabilityException;
use App\Exceptions\ReservationOutsideStayWindowException;
use App\Exceptions\ReservationStateException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Exceptions\RoomOutOfOrderException;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * CheckInReservationAction without HTTP (Phase 3, D-01, D-02, D-04).
 *
 * The concurrency cases are the sequential form (stale model, a rival already
 * committed). SQLite serialises writers, so true parallel races are a
 * MySQL-only guarantee of the reservation / room-type row locks.
 */
class CheckInReservationActionTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;
    private User $actor;
    private CheckInReservationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
        $this->type   = RoomType::factory()->create();
        $this->actor  = User::factory()->create();
        $this->action = app(CheckInReservationAction::class);
    }

    private function room(string $number, string $status = 'available', ?RoomType $type = null): Room
    {
        return Room::factory()->create(['room_type_id' => ($type ?? $this->type)->id, 'number' => $number, 'status' => $status]);
    }

    private function confirmedStay(string $in = '2027-03-10', string $out = '2027-03-12', ?Room $room = null): Reservation
    {
        $reservation = Reservation::factory()->confirmed()->create(['check_in' => $in, 'check_out' => $out]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $this->type->id,
            'room_id'        => $room?->id,
        ]);
        return $reservation;
    }

    /** @return array{0: ?int, 1: string, 2: ?string} room_id, status, checked_in_at */
    private function state(Reservation $reservation): array
    {
        $fresh = $reservation->fresh();
        return [$fresh->rooms()->first()?->room_id, $fresh->status->value, $fresh->checked_in_at?->toIso8601String()];
    }

    public function test_returns_data_and_code(): void
    {
        $room        = $this->room('101');
        $reservation = $this->confirmedStay(room: $room);

        $result = $this->action->handle($reservation, null, $this->actor);

        $this->assertSame(['data', 'code'], array_keys($result));
        $this->assertSame(200, $result['code']);
        $this->assertInstanceOf(Reservation::class, $result['data']);
        $this->assertSame(ReservationStatus::CHECKED_IN, $result['data']->status);
        $this->assertTrue($result['data']->relationLoaded('rooms'));
        $this->assertTrue($result['data']->relationLoaded('guest'));
        $this->assertTrue($result['data']->rooms->first()->relationLoaded('room'));
        $this->assertTrue($result['data']->rooms->first()->relationLoaded('roomType'));
    }

    public function test_refusals_write_nothing(): void
    {
        $free        = $this->room('101');
        $maintenance = $this->room('102', 'maintenance');
        $foreign     = $this->room('301', 'available', RoomType::factory()->create());

        $reservation = $this->confirmedStay(room: $free);
        $before      = $this->state($reservation);

        $cases = [
            RoomOutOfOrderException::class               => fn () => $this->action->handle($reservation, $maintenance, $this->actor),
            ReservationStateException::class             => fn () => $this->action->handle($reservation, $foreign, $this->actor),
            ReservationOutsideStayWindowException::class => function () use ($free) {
                $future = $this->confirmedStay('2027-03-20', '2027-03-22', $free);
                return $this->action->handle($future, null, $this->actor);
            },
        ];

        foreach ($cases as $exception => $call) {
            try {
                $call();
                $this->fail("{$exception} expected");
            } catch (\Throwable $e) {
                $this->assertInstanceOf($exception, $e);
            }
        }

        $this->assertSame($before, $this->state($reservation));
        $this->assertSame(1, Reservation::where('status', ReservationStatus::CONFIRMED)->whereDate('check_in', '2027-03-20')->count());
    }

    public function test_a_stale_confirmed_model_cannot_check_in_twice(): void
    {
        Event::fake([GuestCheckedIn::class]);
        $room        = $this->room('101');
        $reservation = $this->confirmedStay(room: $room);
        $stale       = Reservation::find($reservation->id);

        $this->action->handle(Reservation::find($reservation->id), null, $this->actor);
        $stamp = $reservation->fresh()->checked_in_at;

        $this->travelTo(Carbon::parse('2027-03-10 10:00:00'));
        $this->assertSame(ReservationStatus::CONFIRMED, $stale->status, 'the stale copy still reads confirmed');

        try {
            $this->action->handle($stale, null, $this->actor);
            $this->fail('ReservationStateException expected');
        } catch (ReservationStateException $e) {
            $this->assertSame(['status' => 'checked_in', 'allowed' => ['confirmed']], $e->context());
        }

        $this->assertEquals($stamp, $reservation->fresh()->checked_in_at);
        Event::assertDispatchedTimes(GuestCheckedIn::class, 1);
    }

    public function test_two_reservations_racing_for_the_last_room(): void
    {
        // Two rooms of the type, one out of order: 101 is the last free room.
        $last = $this->room('101');
        $this->room('102', 'maintenance');
        $a    = $this->confirmedStay();
        $b    = $this->confirmedStay();

        $this->action->handle($a, null, $this->actor);
        $this->assertSame($last->id, $a->fresh()->rooms()->first()->room_id);

        $this->expectException(NoAvailabilityException::class);
        try {
            $this->action->handle($b, null, $this->actor);
        } finally {
            $this->assertSame([null, 'confirmed', null], $this->state($b));
        }
    }

    public function test_explicit_room_just_taken_by_another_check_in_is_refused(): void
    {
        $this->room('101');
        $r102 = $this->room('102');
        $a    = $this->confirmedStay();
        $b    = $this->confirmedStay();

        $this->action->handle($a, $r102, $this->actor);

        $this->expectException(RoomAlreadyAssignedException::class);
        try {
            $this->action->handle($b, $r102, $this->actor);
        } finally {
            $this->assertSame([null, 'confirmed', null], $this->state($b));
        }
    }

    public function test_early_override_boundary(): void
    {
        $this->room('101');
        $this->room('102');

        // Exactly the day before arrival, with the flag: allowed.
        $this->travelTo(Carbon::parse('2027-03-09 09:00:00'));
        $dayBefore = $this->confirmedStay();
        $this->action->handle($dayBefore, null, $this->actor, true, 'Night flight');
        $this->assertSame(ReservationStatus::CHECKED_IN, $dayBefore->fresh()->status);

        // Two days before, with the flag: refused.
        $this->travelTo(Carbon::parse('2027-03-08 09:00:00'));
        $twoDays = $this->confirmedStay();
        try {
            $this->action->handle($twoDays, null, $this->actor, true, 'Too early');
            $this->fail('ReservationOutsideStayWindowException expected');
        } catch (ReservationOutsideStayWindowException $e) {
            $this->assertSame([
                'check_in'  => '2027-03-10',
                'check_out' => '2027-03-12',
                'today'     => '2027-03-08',
            ], $e->context());
        }
        $this->assertSame([null, 'confirmed', null], $this->state($twoDays));

        // The day before without the flag: refused.
        $this->travelTo(Carbon::parse('2027-03-09 09:00:00'));
        $noFlag = $this->confirmedStay();
        $this->expectException(ReservationOutsideStayWindowException::class);
        $this->action->handle($noFlag, null, $this->actor);
    }
}
