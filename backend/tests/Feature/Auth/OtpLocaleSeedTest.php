<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Models\Guest;
use App\Support\TranslatableRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Phase 9.1 D-03: a guest created by verify-otp starts with the locale the app
 * is speaking (negotiated by SetLocale), so an app that mirrors
 * `preferred_locale` on first login is not flipped to English. An existing
 * guest's choice is never overwritten by a header.
 */
class OtpLocaleSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OtpDispatcher::reset();
    }

    private function signIn(string $phone, array $headers = []): void
    {
        RateLimiter::clear("otp:min:{$phone}:login");
        RateLimiter::clear("otp:hour:{$phone}:login");

        $this->withHeaders($headers)
            ->postJson('/api/auth/guest/request-otp', ['phone' => $phone, 'channel' => 'sms', 'purpose' => 'login'])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/auth/guest/verify-otp', [
                'phone'   => $phone,
                'code'    => OtpDispatcher::lastCode(),
                'purpose' => 'login',
            ])
            ->assertOk();
    }

    public function test_new_guest_takes_the_request_locale(): void
    {
        $this->signIn('+963911111111', ['Accept-Language' => 'fr']);

        $this->assertSame('fr', Guest::byPhone('+963911111111')->first()->preferred_locale);
    }

    public function test_new_guest_takes_the_negotiated_locale_from_a_weighted_header(): void
    {
        $this->signIn('+963922222222', ['Accept-Language' => 'tr-TR,tr;q=0.9']);

        $this->assertSame('tr', Guest::byPhone('+963922222222')->first()->preferred_locale);
    }

    public function test_new_guest_without_a_header_gets_the_app_fallback(): void
    {
        $supported = TranslatableRules::locales();
        $expected = in_array(config('app.locale'), $supported, true) ? config('app.locale') : $supported[0];

        $this->signIn('+963933333333');

        $this->assertSame($expected, Guest::byPhone('+963933333333')->first()->preferred_locale);
    }

    public function test_existing_guest_keeps_their_locale(): void
    {
        Guest::factory()->phoneVerified()->create(['phone' => '+963944444444', 'preferred_locale' => 'ar']);

        $this->signIn('+963944444444', ['Accept-Language' => 'fr']);

        $this->assertSame('ar', Guest::byPhone('+963944444444')->first()->preferred_locale);
        $this->assertSame(1, Guest::count());
    }
}
