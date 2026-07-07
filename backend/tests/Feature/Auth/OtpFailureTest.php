<?php
namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_otp_returns_otp_expired(): void
    {
        OtpCode::factory()->expired()->create(['identifier'=>'+96391111111','purpose'=>'login']);
        $this->postJson('/api/auth/guest/verify-otp', ['phone'=>'+96391111111','code'=>'123456','purpose'=>'login'])
             ->assertStatus(422)->assertJson(['error_code'=>'otp_expired']);
    }

    public function test_invalid_code_returns_otp_invalid_and_increments_attempts(): void
    {
        OtpCode::factory()->create(['identifier'=>'+96392222222','purpose'=>'login']);
        $this->postJson('/api/auth/guest/verify-otp', ['phone'=>'+96392222222','code'=>'000000','purpose'=>'login'])
             ->assertStatus(422)->assertJson(['error_code'=>'otp_invalid']);
        $this->assertDatabaseHas('otp_codes', ['identifier'=>'+96392222222','attempts'=>1]);
    }

    public function test_locked_after_5_failed_attempts(): void
    {
        OtpCode::factory()->locked()->create(['identifier'=>'+96393333333','purpose'=>'login']);
        $this->postJson('/api/auth/guest/verify-otp', ['phone'=>'+96393333333','code'=>'999999','purpose'=>'login'])
             ->assertStatus(429)->assertJson(['error_code'=>'otp_locked']);
    }
}
