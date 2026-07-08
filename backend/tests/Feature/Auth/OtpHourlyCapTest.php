<?php
namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Actions\Auth\RequestOtpAction;
use App\Exceptions\TooManyRequestsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpHourlyCapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OtpDispatcher::reset();
    }

    public function test_sixth_request_within_hour_is_rate_limited(): void
    {
        $phone   = '+96393333333';
        $purpose = 'login';

        RateLimiter::clear("otp:min:{$phone}:{$purpose}");
        RateLimiter::clear("otp:hour:{$phone}:{$purpose}");

        // 5 successful requests — clear the 1/min gate between each to simulate different minutes
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::clear("otp:min:{$phone}:{$purpose}");
            $this->postJson('/api/auth/guest/request-otp', [
                'phone'   => $phone,
                'channel' => 'sms',
                'purpose' => $purpose,
            ])->assertStatus(200);
        }

        // 6th request — minute gate cleared, but 5/hr cap is exhausted
        RateLimiter::clear("otp:min:{$phone}:{$purpose}");
        $this->postJson('/api/auth/guest/request-otp', [
            'phone'   => $phone,
            'channel' => 'sms',
            'purpose' => $purpose,
        ])->assertStatus(429)
          ->assertJson(['success' => false, 'error_code' => 'too_many_requests']);
    }

    public function test_hourly_cap_applies_to_booking_verification_purpose(): void
    {
        // RequestOtpRequest only allows purpose in:login,register at the HTTP layer.
        // Test booking_verification directly at the action level so P1 validation is unchanged.
        $phone   = '+96393333334';
        $purpose = 'booking_verification';

        RateLimiter::clear("otp:min:{$phone}:{$purpose}");
        RateLimiter::clear("otp:hour:{$phone}:{$purpose}");

        $action = app(RequestOtpAction::class);

        // 5 successful action calls — clear the minute gate between each
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::clear("otp:min:{$phone}:{$purpose}");
            $action->handle($phone, 'sms', $purpose);
        }

        // 6th call — minute gate cleared, hour gate blocks
        RateLimiter::clear("otp:min:{$phone}:{$purpose}");
        $this->expectException(TooManyRequestsException::class);
        $action->handle($phone, 'sms', $purpose);
    }
}
