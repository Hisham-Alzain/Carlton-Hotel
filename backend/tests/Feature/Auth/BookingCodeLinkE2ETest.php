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

/**
 * @group p4
 *
 * Full E2E: booking-code → guest device-link flow.
 * - App-created reservation (guest_id already set): linking still issues a token for the device.
 * - OTA/stub reservation (guest_id null, contact fields on reservation): linking creates/finds guest
 *   and writes guest_id onto the reservation.
 */
class BookingCodeLinkE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        OtpDispatcher::reset();
    }

    private function clearRateLimits(string $identifier): void
    {
        RateLimiter::clear("otp:min:{$identifier}:booking_link");
        RateLimiter::clear("otp:hour:{$identifier}:booking_link");
    }

    public function test_ota_reservation_links_guest_end_to_end(): void
    {
        // Reservation with no guest account yet (OTA / stub import)
        $reservation = Reservation::factory()->create([
            'booking_code' => 'CARL-KMN12345',
            'last_name'    => 'Smith',
            'phone'        => '+96399111222',
            'guest_id'     => null,
        ]);

        $this->clearRateLimits('+96399111222');

        // Step 1: guest provides booking_code + second factor → OTP sent
        $step1 = $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-KMN12345',
            'last_name'    => 'Smith',
        ]);

        $step1->assertOk()->assertJsonStructure(['data' => ['identifier_masked', 'channel']]);
        $this->assertCount(1, OtpDispatcher::allSent());

        // Step 2: verify OTP with booking_code → guest created, reservation linked
        $rawCode = '654321';
        $otp = OtpCode::where('identifier', '+96399111222')
            ->where('purpose', OtpCode::PURPOSE_BOOKING_LINK)
            ->latest()->first();
        $otp->update(['code_hash' => Hash::make($rawCode)]);

        $step2 = $this->postJson('/api/auth/guest/verify-otp', [
            'phone'        => '+96399111222',
            'code'         => $rawCode,
            'purpose'      => OtpCode::PURPOSE_BOOKING_LINK,
            'booking_code' => 'CARL-KMN12345',
        ]);

        $step2->assertOk()
              ->assertJsonStructure(['data' => ['guest', 'token']]);

        // OTP consumed
        $this->assertNotNull(OtpCode::where('identifier', '+96399111222')->first()->consumed_at);

        // Reservation now has guest_id
        $guest = Guest::where('phone', '+96399111222')->firstOrFail();
        $this->assertEquals($guest->id, $reservation->fresh()->guest_id);
    }

    public function test_app_created_reservation_can_also_be_linked(): void
    {
        $existingGuest = Guest::factory()->create(['phone' => '+96399333444']);
        $reservation   = Reservation::factory()->create([
            'booking_code' => 'CARL-APP99900',
            'last_name'    => 'Jones',
            'phone'        => '+96399333444',
            'guest_id'     => $existingGuest->id,
        ]);

        $this->clearRateLimits('+96399333444');

        // Link still issues OTP and returns a token — guest must re-authenticate on new device
        $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-APP99900',
            'last_name'    => 'Jones',
        ])->assertOk();

        $rawCode = '111222';
        $otp = OtpCode::where('identifier', '+96399333444')
            ->where('purpose', OtpCode::PURPOSE_BOOKING_LINK)
            ->latest()->first();
        $otp->update(['code_hash' => Hash::make($rawCode)]);

        $this->postJson('/api/auth/guest/verify-otp', [
            'phone'        => '+96399333444',
            'code'         => $rawCode,
            'purpose'      => OtpCode::PURPOSE_BOOKING_LINK,
            'booking_code' => 'CARL-APP99900',
        ])->assertOk()->assertJsonStructure(['data' => ['guest', 'token']]);

        // guest_id already set — unchanged
        $this->assertEquals($existingGuest->id, $reservation->fresh()->guest_id);
    }

    public function test_wrong_second_factor_does_not_send_otp(): void
    {
        Reservation::factory()->create([
            'booking_code' => 'CARL-SECRETKM',
            'last_name'    => 'Real',
            'phone'        => '+96399555666',
            'guest_id'     => null,
        ]);

        $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-SECRETKM',
            'last_name'    => 'Impostor',
        ])->assertStatus(404)->assertJson(['success' => false]);

        $this->assertEmpty(OtpDispatcher::allSent());
    }
}
