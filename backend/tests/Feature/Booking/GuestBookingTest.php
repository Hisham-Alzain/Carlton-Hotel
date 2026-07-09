<?php

namespace Tests\Feature\Booking;

use App\Models\Guest;
use App\Models\OtpCode;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuestBookingTest extends TestCase
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

    public function test_authenticated_guest_can_book_in_one_step(): void
    {
        $rt    = $this->makeRoomType();
        $guest = Guest::factory()->create();
        $token = $guest->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/reservations', [
            'room_type_uuid' => $rt->uuid,
            'check_in'       => '2027-02-01',
            'check_out'      => '2027-02-05',
            'payment_method' => 'on_arrival',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', Reservation::STATUS_PENDING)
            ->assertJsonStructure(['data' => ['uuid', 'booking_code', 'total_usd']]);

        $this->assertStringStartsWith('CARL-', $res->json('data.booking_code'));
    }

    public function test_authenticated_path_ignores_body_contact_fields(): void
    {
        $rt    = $this->makeRoomType();
        $guest = Guest::factory()->create(['email' => 'real@example.com']);
        $token = $guest->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/reservations', [
            'room_type_uuid' => $rt->uuid,
            'check_in'       => '2027-02-01',
            'check_out'      => '2027-02-05',
            'payment_method' => 'on_arrival',
            'email'          => 'imposter@example.com', // must be ignored
        ])->assertStatus(201);

        // Guest identity unchanged
        $this->assertEquals('real@example.com', $guest->fresh()->email);
        // Reservation is linked to the token guest
        $this->assertEquals($guest->id, Reservation::first()->guest_id);
    }

    public function test_guest_booking_two_step_flow(): void
    {
        $rt = $this->makeRoomType();

        // Step 1: storeAsGuest
        $step1 = $this->postJson('/api/reservations/guest', [
            'room_type_uuid' => $rt->uuid,
            'check_in'       => '2027-02-01',
            'check_out'      => '2027-02-03',
            'first_name'     => 'John',
            'last_name'      => 'Doe',
            'email'          => 'john@example.com',
            'payment_method' => 'on_arrival',
        ]);

        $step1->assertOk()->assertJsonStructure(['data' => ['reservation_uuid', 'identifier_masked', 'channel']]);
        $reservationUuid = $step1->json('data.reservation_uuid');

        // Reservation should be pending_verification
        $reservation = Reservation::where('uuid', $reservationUuid)->first();
        $this->assertEquals(Reservation::STATUS_PENDING_VERIFICATION, $reservation->status);
        $this->assertNotNull($reservation->hold_expires_at);

        // Step 2: verifyGuestBooking
        $otp = OtpCode::where('identifier', 'john@example.com')
            ->where('purpose', OtpCode::PURPOSE_BOOKING_VERIFICATION)
            ->latest()->first();
        $rawCode = '123456';
        $otp->update(['code_hash' => Hash::make($rawCode)]);

        $step2 = $this->postJson('/api/reservations/guest/verify', [
            'reservation_uuid' => $reservationUuid,
            'email'            => 'john@example.com',
            'otp_code'         => $rawCode,
        ]);

        $step2->assertOk()
            ->assertJsonStructure(['data' => ['reservation', 'guest', 'token']])
            ->assertJsonPath('data.reservation.status', Reservation::STATUS_PENDING);

        $this->assertEquals(Reservation::STATUS_PENDING, $reservation->fresh()->status);
    }

    public function test_verify_fails_if_hold_expired(): void
    {
        $rt   = $this->makeRoomType();
        $guest = Guest::factory()->create(['email' => 'late@example.com']);

        $reservation = Reservation::factory()->expiredHold()->create([
            'guest_id' => $guest->id,
            'check_in' => '2027-02-01', 'check_out' => '2027-02-03',
        ]);

        // Create a valid OTP for the identifier
        $otp = OtpCode::create([
            'identifier'  => 'late@example.com',
            'channel'     => OtpCode::CHANNEL_EMAIL,
            'purpose'     => OtpCode::PURPOSE_BOOKING_VERIFICATION,
            'code_hash'   => Hash::make('999999'),
            'attempts'    => 0,
            'expires_at'  => now()->addMinutes(5),
        ]);

        $this->postJson('/api/reservations/guest/verify', [
            'reservation_uuid' => $reservation->uuid,
            'email'            => 'late@example.com',
            'otp_code'         => '999999',
        ])->assertStatus(422)->assertJsonPath('error_code', 'hold_expired');
    }

    public function test_guest_can_view_own_reservations(): void
    {
        $rt    = $this->makeRoomType();
        $guest = Guest::factory()->create();
        Reservation::factory()->count(2)->create(['guest_id' => $guest->id]);
        $token = $guest->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/reservations')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }
}
