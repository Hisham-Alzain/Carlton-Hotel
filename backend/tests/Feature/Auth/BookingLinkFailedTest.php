<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Models\Reservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /auth/guest/link-booking-code answers every failed lookup with one code
 * (booking_link_failed, 404), one message and no context, so the response never
 * tells a caller which part (code, second factor, contact on file) was wrong.
 * The route is throttled per IP like request-otp.
 */
class BookingLinkFailedTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/auth/guest/link-booking-code';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        OtpDispatcher::reset();
        RateLimiter::clear('otp:min:+96395555555:booking_link');
        RateLimiter::clear('otp:hour:+96395555555:booking_link');
    }

    private function makeReservation(array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'booking_code' => 'CARL-7K2M9XBV',
            'last_name' => 'Doe',
            'phone' => '+96395555555',
            'guest_id' => null,
        ], $overrides));
    }

    /** Decoded body without the per-request id, for identical-body comparison. */
    private function body(TestResponse $response): array
    {
        $json = $response->json();
        unset($json['request_id']);

        return $json;
    }

    public function test_unknown_code_answers_booking_link_failed(): void
    {
        $response = $this->postJson(self::URL, ['booking_code' => 'CARL-ZZZZZZZZ', 'last_name' => 'Doe']);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'booking_link_failed')
            ->assertJsonPath('message', __('custom.errors.booking_link_failed'))
            ->assertJsonPath('context', null);
        $this->assertEmpty(OtpDispatcher::allSent());
    }

    public function test_every_miss_returns_an_identical_body(): void
    {
        $this->makeReservation();
        $this->makeReservation([
            'booking_code' => 'CARL-2KM9XBV3',
            'last_name' => 'Nobody',
            'phone' => null,
        ]);

        $unknown = $this->postJson(self::URL, ['booking_code' => 'CARL-ZZZZZZZZ', 'last_name' => 'Doe']);
        $wrongFactor = $this->postJson(self::URL, ['booking_code' => 'CARL-7K2M9XBV', 'last_name' => 'Wrong']);
        $noContact = $this->postJson(self::URL, ['booking_code' => 'CARL-2KM9XBV3', 'last_name' => 'Nobody']);

        foreach ([$unknown, $wrongFactor, $noContact] as $response) {
            $response->assertStatus(404)->assertJsonPath('error_code', 'booking_link_failed');
        }
        $this->assertSame($this->body($unknown), $this->body($wrongFactor));
        $this->assertSame($this->body($unknown), $this->body($noContact));
        $this->assertEmpty(OtpDispatcher::allSent());
    }

    public function test_correct_code_and_last_name_issues_otp_with_masked_identifier(): void
    {
        $this->makeReservation();

        $this->postJson(self::URL, ['booking_code' => 'CARL-7K2M9XBV', 'last_name' => 'Doe'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['identifier_masked', 'channel']]);

        $this->assertCount(1, OtpDispatcher::allSent());
    }

    public function test_code_without_second_factor_is_a_validation_error(): void
    {
        $this->makeReservation();

        $this->postJson(self::URL, ['booking_code' => 'CARL-7K2M9XBV'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['booking_code']]);
        $this->assertEmpty(OtpDispatcher::allSent());
    }

    public function test_miss_message_is_localized_but_the_code_is_stable(): void
    {
        $response = $this->postJson(
            self::URL,
            ['booking_code' => 'CARL-ZZZZZZZZ', 'last_name' => 'Doe'],
            ['Accept-Language' => 'ar'],
        );

        $response->assertStatus(404)
            ->assertJsonPath('error_code', 'booking_link_failed')
            ->assertJsonPath('message', 'تعذر التحقق من تفاصيل الحجز.');
    }

    public function test_route_is_throttled_at_ten_per_minute(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson(self::URL, ['booking_code' => 'CARL-ZZZZZZZZ', 'last_name' => 'Doe'])
                ->assertStatus(404);
        }

        $this->postJson(self::URL, ['booking_code' => 'CARL-ZZZZZZZZ', 'last_name' => 'Doe'])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'too_many_requests');
    }
}
