<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\OtpDispatcher;
use App\Enums\GuestAccountStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Support\HotelClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DELETE /api/auth/guest/me (Phase 9.1, GACC-01/02, D-06, D-09, D-10).
 */
class GuestAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+963955123456';

    protected function setUp(): void
    {
        parent::setUp();
        OtpDispatcher::reset();
    }

    private function deleteMe(?string $token, array $body = ['confirm' => true], string $locale = 'en')
    {
        $this->app['auth']->forgetGuards();
        $request = $this->withHeaders(['Accept-Language' => $locale]);
        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request->deleteJson('/api/auth/guest/me', $body);
    }

    private function me(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/auth/guest/me');
    }

    private function signIn(string $phone): array
    {
        RateLimiter::clear("otp:min:{$phone}:login");
        RateLimiter::clear("otp:hour:{$phone}:login");
        $this->app['auth']->forgetGuards();

        $this->withHeaders([])->postJson('/api/auth/guest/request-otp', ['phone' => $phone, 'channel' => 'sms', 'purpose' => 'login'])
            ->assertOk();

        return $this->postJson('/api/auth/guest/verify-otp', [
            'phone' => $phone, 'code' => OtpDispatcher::lastCode(), 'purpose' => 'login',
        ])->assertOk()->json('data');
    }

    public function test_happy_path_returns_the_envelope_and_anonymizes(): void
    {
        $guest = Guest::factory()->create(['phone' => self::PHONE]);
        $token = $guest->createToken('app')->plainTextToken;

        $this->deleteMe($token)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.auth.account_deleted', [], 'en'))
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['success', 'message', 'data', 'request_id']);

        $fresh = $guest->fresh();
        $this->assertSame(GuestAccountStatus::DELETED, $fresh->account_status);
        $this->assertNull($fresh->phone);
    }

    public function test_requires_a_token(): void
    {
        $this->deleteMe(null)->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_a_staff_token_is_401_on_the_guest_guard(): void
    {
        $token = User::factory()->create()->createToken('staff')->plainTextToken;

        $this->deleteMe($token)->assertStatus(401);
    }

    /** @return array<string, array{array}> */
    public static function badBodies(): array
    {
        return [
            'empty'       => [[]],
            'false'       => [['confirm' => false]],
            'string no'   => [['confirm' => 'no']],
        ];
    }

    #[DataProvider('badBodies')]
    public function test_confirm_must_be_accepted(array $body): void
    {
        $guest = Guest::factory()->create();
        $token = $guest->createToken('app')->plainTextToken;

        $this->deleteMe($token, $body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['confirm']);

        $this->assertFalse($guest->fresh()->isDeleted());
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['en' => ['en'], 'ar' => ['ar'], 'fr' => ['fr'], 'tr' => ['tr'], 'es' => ['es']];
    }

    #[DataProvider('locales')]
    public function test_validation_attribute_is_translated(string $locale): void
    {
        $token = Guest::factory()->create()->createToken('app')->plainTextToken;

        $message = $this->deleteMe($token, [], $locale)->assertStatus(422)->json('errors.confirm.0');

        $this->assertStringContainsString(__('custom.attributes.confirm', [], $locale), $message);
    }

    #[DataProvider('locales')]
    public function test_blocked_returns_code_and_context(string $locale): void
    {
        $guest = Guest::factory()->create();
        $r = Reservation::factory()->checkedIn()->create([
            'guest_id'  => $guest->id,
            'check_in'  => HotelClock::today()->subDay()->toDateString(),
            'check_out' => HotelClock::today()->addDay()->toDateString(),
        ]);
        $token = $guest->createToken('app')->plainTextToken;

        $this->deleteMe($token, ['confirm' => true], $locale)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'guest_account_deletion_blocked')
            ->assertJsonPath('message', __('custom.errors.guest_account_deletion_blocked', [], $locale))
            ->assertJsonPath('context.reasons', ['active_reservation'])
            ->assertJsonPath('context.booking_codes', [$r->booking_code]);

        $this->assertFalse($guest->fresh()->isDeleted());
    }

    public function test_every_device_is_signed_out_and_the_token_is_dead(): void
    {
        $guest = Guest::factory()->create();
        $a = $guest->createToken('phone')->plainTextToken;
        $b = $guest->createToken('tablet')->plainTextToken;

        $this->deleteMe($a)->assertOk();

        $this->me($a)->assertStatus(401);
        $this->me($b)->assertStatus(401);
        $this->deleteMe($a)->assertStatus(401);
    }

    public function test_throttled_at_five_per_minute(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->checkedIn()->create([
            'guest_id' => $guest->id, 'check_out' => HotelClock::today()->addDay()->toDateString(),
        ]);
        $token = $guest->createToken('app')->plainTextToken;

        for ($i = 0; $i < 5; $i++) {
            $this->deleteMe($token)->assertStatus(422);
        }

        $this->deleteMe($token)->assertStatus(429);
    }

    public function test_signing_in_again_creates_a_new_guest_without_the_old_stays(): void
    {
        $first = $this->signIn(self::PHONE);
        $old = Guest::where('uuid', $first['guest']['uuid'])->first();
        Reservation::factory()->cancelled()->create(['guest_id' => $old->id]);

        $this->deleteMe($first['token'])->assertOk();

        $second = $this->signIn(self::PHONE);
        $this->assertNotSame($first['guest']['uuid'], $second['guest']['uuid']);

        $new = Guest::where('uuid', $second['guest']['uuid'])->first();
        $this->assertSame(0, $new->reservations()->count());
        $this->assertSame(1, $old->reservations()->count());
    }

    public function test_exactly_one_delete_route_is_registered(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/auth/guest/me' && in_array('DELETE', $r->methods(), true));

        $this->assertCount(1, $routes);
        $this->assertContains('throttle:5,1', $routes->first()->gatherMiddleware());
        $this->assertContains('auth:guests', $routes->first()->gatherMiddleware());
    }
}
