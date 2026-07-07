<?php
namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OtpDispatcher::reset();
    }

    public function test_phone_stored_as_e164(): void
    {
        // Syrian local format → should normalize to +963...
        $localPhone = '0912345678'; // local SY format
        RateLimiter::clear("otp:min:+963912345678:register");
        RateLimiter::clear("otp:hour:+963912345678:register");

        $this->postJson('/api/auth/guest/request-otp', [
            'phone'   => $localPhone,
            'channel' => 'sms',
            'purpose' => 'register',
        ])->assertStatus(200);

        $code = OtpDispatcher::lastCode();
        $this->assertNotNull($code, 'OTP was dispatched');

        $normalizedPhone = OtpDispatcher::allSent()[0]['identifier'];
        $this->assertStringStartsWith('+963', $normalizedPhone);

        $this->postJson('/api/auth/guest/verify-otp', [
            'phone'   => $localPhone,
            'code'    => $code,
            'purpose' => 'register',
        ])->assertStatus(200);

        $guest = Guest::where('phone', $normalizedPhone)->first();
        $this->assertNotNull($guest, 'Guest created with E.164 phone');
        $this->assertEquals('SY', $guest->phone_country);
    }
}
