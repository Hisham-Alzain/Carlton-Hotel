<?php

namespace Tests\Feature\Auth;

use App\Models\DeviceToken;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ACCESS-01: `POST /api/auth/guest/logout` (D-08, D-09, D-10).
 *
 * Every request carries a real bearer token (`createToken()->plainTextToken`).
 * The acting-as helper would leave `currentAccessToken()` null and silently
 * exercise the delete-all fallback instead of the revoke-this-token path.
 */
class GuestLogoutTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/auth/guest/logout';

    /** @return array{0: Guest, 1: string, 2: int} guest, plain token, token row id */
    private function guestWithToken(?Guest $guest = null): array
    {
        $guest ??= Guest::factory()->create();
        $new   = $guest->createToken('t');

        return [$guest, $new->plainTextToken, $new->accessToken->getKey()];
    }

    private function forgetGuards(): void
    {
        $this->app->get('auth')->forgetGuards();
    }

    public function test_guest_logout_revokes_current_token(): void
    {
        [, $token, $tokenId] = $this->guestWithToken();

        $this->withToken($token)->postJson(self::URL)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/guest/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_revoked_token_is_rejected_on_every_guest_route(): void
    {
        [, $token] = $this->guestWithToken();

        $this->withToken($token)->postJson(self::URL)->assertOk();

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('auth:guests', $route->gatherMiddleware(), true))
            ->values();

        // The scan must never pass by matching nothing.
        $this->assertNotEmpty($routes);
        $this->assertContains('api/auth/guest/logout', $routes->map(fn ($r) => $r->uri())->all());

        foreach ($routes as $route) {
            $uri    = preg_replace('/\{[^}]+\}/', (string) Str::uuid(), $route->uri());
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');

            $this->forgetGuards();
            $response = $this->withToken($token)->json($method, '/'.$uri);

            $this->assertSame(401, $response->status(), "{$method} /{$uri} accepted a revoked guest token");
            $this->assertSame('unauthorized', $response->json('error_code'), "{$method} /{$uri}");
        }
    }

    public function test_logout_keeps_the_guests_other_tokens_valid(): void
    {
        [$guest, $tokenA] = $this->guestWithToken();
        [, $tokenB, $idB] = $this->guestWithToken($guest);

        $this->withToken($tokenA)->postJson(self::URL)->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $idB]);

        $this->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/auth/guest/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_second_logout_with_revoked_token_returns_401(): void
    {
        [, $token] = $this->guestWithToken();

        $this->withToken($token)->postJson(self::URL)->assertOk();

        $this->forgetGuards();
        $this->withToken($token)->postJson(self::URL)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $this->postJson(self::URL)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_staff_token_cannot_use_guest_logout(): void
    {
        $user      = User::factory()->create();
        $new       = $user->createToken('t');
        $staffToken = $new->plainTextToken;

        $this->withToken($staffToken)->postJson(self::URL)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $new->accessToken->getKey()]);

        $this->forgetGuards();
        $this->withToken($staffToken)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid);
    }

    public function test_logout_deregisters_owned_device_token(): void
    {
        [$guest, $token] = $this->guestWithToken();
        $owned = DeviceToken::factory()->create(['guest_id' => $guest->id]);
        $kept  = DeviceToken::factory()->create(['guest_id' => $guest->id]);

        $this->withToken($token)->postJson(self::URL, ['device_token' => $owned->token])
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('device_tokens', ['id' => $owned->id]);
        // Only the named device is deregistered: no sign-out-everywhere.
        $this->assertDatabaseHas('device_tokens', ['id' => $kept->id, 'guest_id' => $guest->id]);
    }

    public function test_logout_ignores_device_token_owned_by_another_guest(): void
    {
        [, $token, $tokenId] = $this->guestWithToken();
        $otherGuest = Guest::factory()->create();
        $foreign    = DeviceToken::factory()->create(['guest_id' => $otherGuest->id]);

        $this->withToken($token)->postJson(self::URL, ['device_token' => $foreign->token])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'id'       => $foreign->id,
            'guest_id' => $otherGuest->id,
            'token'    => $foreign->token,
        ]);
        // The caller's own session is still revoked.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_logout_ignores_unknown_device_token(): void
    {
        [$guest, $token, $tokenId] = $this->guestWithToken();
        $row = DeviceToken::factory()->create(['guest_id' => $guest->id]);

        $this->withToken($token)->postJson(self::URL, ['device_token' => 'unknown-'.Str::random(40)])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', ['id' => $row->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_logout_without_device_token_leaves_device_tokens_untouched(): void
    {
        [$guest, $token] = $this->guestWithToken();
        $rows = DeviceToken::factory()->count(2)->create(['guest_id' => $guest->id]);

        $this->withToken($token)->postJson(self::URL)->assertOk();

        foreach ($rows as $row) {
            $this->assertDatabaseHas('device_tokens', ['id' => $row->id, 'guest_id' => $guest->id]);
        }
        $this->assertSame(2, DeviceToken::where('guest_id', $guest->id)->count());
    }

    public function test_device_token_longer_than_500_chars_returns_422(): void
    {
        [, $token, $tokenId] = $this->guestWithToken();

        $this->withToken($token)->postJson(self::URL, ['device_token' => str_repeat('a', 501)])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['device_token']]);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/guest/me')->assertOk();
    }

    public function test_non_string_device_token_returns_422(): void
    {
        [, $token, $tokenId] = $this->guestWithToken();

        $this->withToken($token)->postJson(self::URL, ['device_token' => ['x']])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['device_token']]);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/guest/me')->assertOk();
    }
}
