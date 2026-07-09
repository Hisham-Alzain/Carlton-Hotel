<?php
namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Models\Guest;
use App\Models\OtpCode;
use App\Models\Reservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BookingCodeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        OtpDispatcher::reset();
    }

    private function makeReservation(array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'booking_code' => 'CARL-7K2M9XBV',
            'last_name'    => 'Doe',
            'phone'        => '+96395555555',
            'guest_id'     => null,
        ], $overrides));
    }

    public function test_code_alone_rejected(): void
    {
        $this->makeReservation();
        $this->postJson('/api/auth/guest/link-booking-code', ['booking_code' => 'CARL-7K2M9XBV'])
             ->assertStatus(422);
        $this->assertEmpty(OtpDispatcher::allSent());
    }

    public function test_code_plus_last_name_issues_otp(): void
    {
        $this->makeReservation();
        RateLimiter::clear('otp:min:+96395555555:booking_link');
        RateLimiter::clear('otp:hour:+96395555555:booking_link');

        $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-7K2M9XBV',
            'last_name'    => 'Doe',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertCount(1, OtpDispatcher::allSent());
    }

    public function test_booking_link_verify_links_reservation_to_guest(): void
    {
        $reservation = $this->makeReservation();
        $phone       = '+96395555556';

        OtpCode::create([
            'identifier'  => $phone,
            'channel'     => OtpCode::CHANNEL_SMS,
            'code_hash'   => Hash::make('123456'),
            'purpose'     => OtpCode::PURPOSE_BOOKING_LINK,
            'attempts'    => 0,
            'expires_at'  => now()->addMinutes(5),
            'consumed_at' => null,
        ]);

        // Verify OTP and pass booking_code to trigger linking
        $this->postJson('/api/auth/guest/verify-otp', [
            'phone'        => $phone,
            'code'         => '123456',
            'purpose'      => OtpCode::PURPOSE_BOOKING_LINK,
            'booking_code' => $reservation->booking_code,
        ])->assertOk()
          ->assertJson(['success' => true])
          ->assertJsonStructure(['data' => ['guest', 'token']]);

        // OTP must be consumed
        $this->assertNotNull(
            OtpCode::where('identifier', $phone)->first()->consumed_at
        );
    }

    public function test_wrong_second_factor_generic_failure(): void
    {
        $this->makeReservation();
        $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-7K2M9XBV',
            'last_name'    => 'Wrong',
        ])->assertStatus(404)->assertJson(['success' => false]);
        $this->assertEmpty(OtpDispatcher::allSent());
    }
}
