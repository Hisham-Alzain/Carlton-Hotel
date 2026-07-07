<?php
namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BookingCodeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OtpDispatcher::reset();
    }

    private function makeReservation(): Reservation
    {
        return Reservation::create([
            'booking_code' => 'CARL-7K2M9X',
            'last_name'    => 'Doe',
            'phone'        => '+96395555555',
        ]);
    }

    public function test_code_alone_rejected(): void
    {
        $this->makeReservation();
        $this->postJson('/api/auth/guest/link-booking-code', ['booking_code' => 'CARL-7K2M9X'])
             ->assertStatus(422);
        $this->assertEmpty(OtpDispatcher::allSent());
    }

    public function test_code_plus_last_name_issues_otp(): void
    {
        $this->makeReservation();
        RateLimiter::clear('otp:min:+96395555555:booking_link');
        RateLimiter::clear('otp:hour:+96395555555:booking_link');

        $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-7K2M9X',
            'last_name'    => 'Doe',
        ])->assertStatus(200)->assertJson(['success'=>true]);

        $this->assertCount(1, OtpDispatcher::allSent());
    }

    public function test_wrong_second_factor_generic_failure(): void
    {
        $this->makeReservation();
        $this->postJson('/api/auth/guest/link-booking-code', [
            'booking_code' => 'CARL-7K2M9X',
            'last_name'    => 'Wrong',
        ])->assertStatus(404)->assertJson(['success'=>false]);
        $this->assertEmpty(OtpDispatcher::allSent());
    }
}
