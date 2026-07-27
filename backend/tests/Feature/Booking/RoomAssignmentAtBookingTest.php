<?php

namespace Tests\Feature\Booking;

use App\Actions\Booking\AssignRoomAction;
use App\Actions\Booking\CreateReservationAction;
use App\Adapters\DirectAdapter;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Exceptions\NoAvailabilityException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rooms are reserved when the booking is made, not at check-in, so a guest can
 * be shown "Room 801" straight away.
 */
class RoomAssignmentAtBookingTest extends TestCase
{
    use RefreshDatabase;

    private function book(Guest $guest, RoomType $roomType, string $checkIn = '2027-06-01', string $checkOut = '2027-06-05'): Reservation
    {
        return app(CreateReservationAction::class)->handle($guest, [
            'room_type_id'   => $roomType->id,
            'check_in'       => $checkIn,
            'check_out'      => $checkOut,
            'payment_method' => PaymentMethod::ON_ARRIVAL,
        ], new DirectAdapter())['data'];
    }

    public function test_booking_reserves_a_specific_room(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        $room     = Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);

        $reservation = $this->book(Guest::factory()->create(), $roomType);

        $this->assertSame($room->id, $reservation->rooms->first()->room_id);
    }

    public function test_the_room_number_is_visible_on_an_upcoming_stay(): void
    {
        $guest    = Guest::factory()->create();
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);

        $this->book($guest, $roomType, now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString());

        $this->actingAs($guest, 'guests')
            ->getJson('/api/stays/upcoming')
            ->assertOk()
            ->assertJsonPath('data.0.room_number', '801');
    }

    public function test_two_bookings_get_two_different_rooms(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '802']);

        $first  = $this->book(Guest::factory()->create(), $roomType);
        $second = $this->book(Guest::factory()->create(), $roomType);

        $this->assertNotSame(
            $first->rooms->first()->room_id,
            $second->rooms->first()->room_id,
        );
    }

    public function test_lowest_numbered_free_room_is_chosen(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '805']);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);

        $reservation = $this->book(Guest::factory()->create(), $roomType);

        $this->assertSame('801', Room::find($reservation->rooms->first()->room_id)->number);
    }

    public function test_the_same_room_is_reusable_for_non_overlapping_dates(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        $room     = Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);

        $first  = $this->book(Guest::factory()->create(), $roomType, '2027-06-01', '2027-06-05');
        $second = $this->book(Guest::factory()->create(), $roomType, '2027-06-10', '2027-06-14');

        $this->assertSame($room->id, $first->rooms->first()->room_id);
        $this->assertSame($room->id, $second->rooms->first()->room_id);
    }

    public function test_booking_fails_when_every_room_of_the_type_is_taken(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);

        $this->book(Guest::factory()->create(), $roomType);

        $this->expectException(NoAvailabilityException::class);
        $this->book(Guest::factory()->create(), $roomType);
    }

    public function test_inactive_rooms_are_never_reserved(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801', 'is_active' => false]);

        $this->expectException(NoAvailabilityException::class);
        $this->book(Guest::factory()->create(), $roomType);
    }

    // ── Check-in ──────────────────────────────────────────────────────────

    public function test_check_in_without_a_room_uses_the_reserved_one(): void
    {
        $roomType    = RoomType::factory()->create(['base_price_usd' => 100]);
        $room        = Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);
        $reservation = $this->book(Guest::factory()->create(), $roomType);
        $reservation->update(['status' => ReservationStatus::CONFIRMED]);

        $result = app(AssignRoomAction::class)->handle($reservation);

        $this->assertSame(ReservationStatus::CHECKED_IN, $result['data']->status);
        $this->assertSame($room->id, $result['data']->rooms->first()->room_id);
        $this->assertNotNull($result['data']->checked_in_at);
    }

    public function test_staff_can_move_the_guest_to_another_room_at_check_in(): void
    {
        $roomType    = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);
        $other       = Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '802']);
        $reservation = $this->book(Guest::factory()->create(), $roomType);
        $reservation->update(['status' => ReservationStatus::CONFIRMED]);

        $result = app(AssignRoomAction::class)->handle($reservation, $other);

        $this->assertSame($other->id, $result['data']->rooms->first()->room_id);
    }

    public function test_moving_into_a_room_another_booking_holds_is_refused(): void
    {
        $roomType = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '801']);
        $second   = Room::factory()->create(['room_type_id' => $roomType->id, 'number' => '802']);

        $mine   = $this->book(Guest::factory()->create(), $roomType);
        $theirs = $this->book(Guest::factory()->create(), $roomType);
        $mine->update(['status' => ReservationStatus::CONFIRMED]);

        // 802 belongs to the other booking, which is only `pending` — still held.
        $this->assertSame($second->id, $theirs->rooms->first()->room_id);

        $this->expectException(RoomAlreadyAssignedException::class);
        app(AssignRoomAction::class)->handle($mine, $second);
    }
}
