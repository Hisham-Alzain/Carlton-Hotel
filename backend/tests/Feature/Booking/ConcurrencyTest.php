<?php

namespace Tests\Feature\Booking;

use App\Actions\Booking\CreateReservationAction;
use App\Actions\Booking\ReleaseExpiredHoldsAction;
use App\Adapters\DirectAdapter;
use App\Exceptions\NoAvailabilityException;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function bookingData(RoomType $rt, string $checkIn = '2027-06-01', string $checkOut = '2027-06-05'): array
    {
        return [
            'room_type_id'  => $rt->id,
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'payment_method'=> Reservation::PAYMENT_ON_ARRIVAL,
        ];
    }

    public function test_only_one_reservation_succeeds_when_last_room_taken(): void
    {
        $rt     = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $guest1 = Guest::factory()->create();
        $guest2 = Guest::factory()->create();
        $action = app(CreateReservationAction::class);
        $channel= new DirectAdapter();

        $success = 0;
        $fail    = 0;

        foreach ([$guest1, $guest2] as $guest) {
            try {
                $action->handle($guest, $this->bookingData($rt), $channel);
                $success++;
            } catch (NoAvailabilityException) {
                $fail++;
            }
        }

        $this->assertEquals(1, $success);
        $this->assertEquals(1, $fail);
        $this->assertEquals(1, Reservation::count());
        $this->assertEquals(1, ReservationRoom::count());
    }

    public function test_soft_hold_blocks_last_room_while_live(): void
    {
        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $hold = Reservation::factory()->pendingVerification()->create([
            'check_in' => '2027-06-01', 'check_out' => '2027-06-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $hold->id, 'room_type_id' => $rt->id]);

        $guest  = Guest::factory()->create();
        $action = app(CreateReservationAction::class);

        $this->expectException(NoAvailabilityException::class);
        $action->handle($guest, $this->bookingData($rt), new DirectAdapter());
    }

    public function test_hold_auto_releases_and_room_becomes_available(): void
    {
        $rt   = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $hold = Reservation::factory()->expiredHold()->create([
            'check_in' => '2027-06-01', 'check_out' => '2027-06-05',
        ]);
        ReservationRoom::factory()->create(['reservation_id' => $hold->id, 'room_type_id' => $rt->id]);

        // Run the release job
        $released = app(ReleaseExpiredHoldsAction::class)->handle();
        $this->assertEquals(1, $released);
        $this->assertEquals(Reservation::STATUS_CANCELLED, $hold->fresh()->status);

        // Room is now bookable again
        $guest  = Guest::factory()->create();
        $result = app(CreateReservationAction::class)->handle($guest, $this->bookingData($rt), new DirectAdapter());
        $this->assertEquals(201, $result['code']);
        $this->assertEquals(Reservation::STATUS_PENDING, $result['data']->status);
    }

    public function test_second_room_makes_both_bookings_succeed(): void
    {
        $rt = RoomType::factory()->create(['base_price_usd' => 100]);
        Room::factory()->count(2)->create(['room_type_id' => $rt->id, 'is_active' => true]);
        $guest1 = Guest::factory()->create();
        $guest2 = Guest::factory()->create();
        $action = app(CreateReservationAction::class);
        $channel= new DirectAdapter();

        $success = 0;
        foreach ([$guest1, $guest2] as $guest) {
            try {
                $action->handle($guest, $this->bookingData($rt), $channel);
                $success++;
            } catch (NoAvailabilityException) {}
        }

        $this->assertEquals(2, $success);
        $this->assertEquals(2, Reservation::count());
    }
}
