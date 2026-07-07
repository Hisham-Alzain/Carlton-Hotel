<?php
namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuance_rate_limited_per_minute(): void
    {
        $phone = '+96394444444';
        RateLimiter::clear("otp:min:{$phone}:login");
        RateLimiter::clear("otp:hour:{$phone}:login");

        $this->postJson('/api/auth/guest/request-otp', ['phone'=>$phone,'channel'=>'sms','purpose'=>'login'])
             ->assertStatus(200);

        $this->postJson('/api/auth/guest/request-otp', ['phone'=>$phone,'channel'=>'sms','purpose'=>'login'])
             ->assertStatus(429)->assertJson(['error_code'=>'too_many_requests']);
    }
}
