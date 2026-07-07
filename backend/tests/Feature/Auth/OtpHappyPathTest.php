<?php
namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpHappyPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OtpDispatcher::reset();
        RateLimiter::clear('otp:min:+96391234567:login');
        RateLimiter::clear('otp:hour:+96391234567:login');
    }

    public function test_otp_issue_and_verify_phone_channel(): void
    {
        $phone = '+96391234567';

        $this->postJson('/api/auth/guest/request-otp', [
            'phone'   => $phone,
            'channel' => 'sms',
            'purpose' => 'login',
        ])->assertStatus(200)->assertJson(['success' => true, 'message' => __('custom.auth.otp_sent')]);

        $code = OtpDispatcher::lastCode();
        $this->assertNotNull($code);

        $res = $this->postJson('/api/auth/guest/verify-otp', [
            'phone'   => $phone,
            'code'    => $code,
            'purpose' => 'login',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertNotEmpty($res->json('data.token'));
        $guest = Guest::byPhone($phone)->first();
        $this->assertNotNull($guest->phone_verified_at);
    }

    public function test_otp_issue_and_verify_email_channel(): void
    {
        $email = 'test@example.com';
        RateLimiter::clear("otp:min:{$email}:register");
        RateLimiter::clear("otp:hour:{$email}:register");

        $this->postJson('/api/auth/guest/request-otp', [
            'email'   => $email,
            'channel' => 'email',
            'purpose' => 'register',
        ])->assertStatus(200);

        $code = OtpDispatcher::lastCode();
        $res = $this->postJson('/api/auth/guest/verify-otp', [
            'email'   => $email,
            'code'    => $code,
            'purpose' => 'register',
        ])->assertStatus(200);

        $guest = Guest::byEmail($email)->first();
        $this->assertNotNull($guest->email_verified_at);
        $this->assertNotEmpty($res->json('data.token'));
    }

    public function test_returning_guest_matched_not_duplicated(): void
    {
        $phone = '+96399876543';
        RateLimiter::clear("otp:min:{$phone}:login");
        RateLimiter::clear("otp:hour:{$phone}:login");

        $existing = Guest::factory()->phoneVerified()->create(['phone' => $phone]);

        $this->postJson('/api/auth/guest/request-otp', ['phone'=>$phone,'channel'=>'sms','purpose'=>'login'])->assertStatus(200);
        $code = OtpDispatcher::lastCode();
        $this->postJson('/api/auth/guest/verify-otp', ['phone'=>$phone,'code'=>$code,'purpose'=>'login'])->assertStatus(200);

        $this->assertSame(1, Guest::byPhone($phone)->count());
        $this->assertEquals($existing->id, Guest::byPhone($phone)->first()->id);
    }
}
