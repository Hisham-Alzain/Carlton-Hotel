<?php

namespace Tests\Feature\Auth;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * ACCESS-03: `PUT /api/auth/password` (D-04, D-05, D-06, D-07, D-11).
 *
 * Real bearer tokens only: the "keep current, revoke others" branch runs only
 * when `currentAccessToken()` is a real PersonalAccessToken.
 */
class StaffPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    /** Must match `throttle:5,1` on the route (keyed per authenticated user). */
    private const LIMIT = 5;

    private const URL          = '/api/auth/password';
    private const PASSWORD     = 'Original-pass-1';
    private const NEW_PASSWORD = 'New-pass-2345';

    private function staff(string $email = 'desk@carlton.test'): User
    {
        return User::factory()->create(['email' => $email, 'password' => self::PASSWORD]);
    }

    private function validPayload(): array
    {
        return [
            'current_password'      => self::PASSWORD,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    private function wrongPayload(): array
    {
        return [
            'current_password'      => 'Not-the-password-9',
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    private function forgetGuards(): void
    {
        $this->app->get('auth')->forgetGuards();
    }

    public function test_password_change_keeps_current_session_and_revokes_others(): void
    {
        $user    = $this->staff();
        $current = $user->createToken('current')->plainTextToken;
        $other   = $user->createToken('other')->plainTextToken;

        $this->withToken($current)->putJson(self::URL, $this->validPayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertOk();

        $this->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_old_password_stops_working_and_new_password_logs_in(): void
    {
        $user  = $this->staff();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->putJson(self::URL, $this->validPayload())->assertOk();

        $this->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'desk@carlton.test', 'password' => self::PASSWORD])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->postJson('/api/auth/login', ['email' => 'desk@carlton.test', 'password' => self::NEW_PASSWORD])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_other_users_tokens_are_untouched(): void
    {
        $user        = $this->staff();
        $bystander   = $this->staff('other@carlton.test');
        $token       = $user->createToken('t')->plainTextToken;
        $theirToken  = $bystander->createToken('t')->plainTextToken;

        $this->withToken($token)->putJson(self::URL, $this->validPayload())->assertOk();

        $this->forgetGuards();
        $this->withToken($theirToken)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.uuid', $bystander->uuid);

        $this->assertTrue(Hash::check(self::PASSWORD, $bystander->fresh()->password));
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $user    = $this->staff();
        $current = $user->createToken('current')->plainTextToken;
        $other   = $user->createToken('other')->plainTextToken;

        $this->withToken($current)->putJson(self::URL, $this->wrongPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->assertSame(2, $user->tokens()->count());

        $this->forgetGuards();
        $this->withToken($current)->getJson('/api/auth/me')->assertOk();
        $this->forgetGuards();
        $this->withToken($other)->getJson('/api/auth/me')->assertOk();
    }

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $user  = $this->staff();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->putJson(self::URL, [
            'current_password'      => self::PASSWORD,
            'password'              => self::NEW_PASSWORD,
            'password_confirmation' => 'Something-else-1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['password']]);

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_new_password_same_as_current_returns_422(): void
    {
        $user  = $this->staff();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->putJson(self::URL, [
            'current_password'      => self::PASSWORD,
            'password'              => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_new_password_shorter_than_8_returns_422(): void
    {
        $user  = $this->staff();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->putJson(self::URL, [
            'current_password'      => self::PASSWORD,
            'password'              => 'Short-7',
            'password_confirmation' => 'Short-7',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['password']]);

        // Boundary: exactly 8 characters is accepted.
        $this->forgetGuards();
        $this->withToken($token)->putJson(self::URL, [
            'current_password'      => self::PASSWORD,
            'password'              => 'Eight-88',
            'password_confirmation' => 'Eight-88',
        ])->assertOk();
    }

    public function test_missing_fields_return_422(): void
    {
        $token = $this->staff()->createToken('t')->plainTextToken;

        $this->withToken($token)->putJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['current_password', 'password']]);
    }

    public function test_unauthenticated_cannot_change_password(): void
    {
        $this->putJson(self::URL, $this->validPayload())
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_guest_token_cannot_change_password(): void
    {
        $guestToken = Guest::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($guestToken)->putJson(self::URL, $this->validPayload())
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_password_change_is_throttled_per_user(): void
    {
        $token = $this->staff()->createToken('t')->plainTextToken;

        for ($attempt = 1; $attempt <= self::LIMIT; $attempt++) {
            $this->withToken($token)->putJson(self::URL, $this->wrongPayload())
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'validation_failed');
        }

        $this->withToken($token)->putJson(self::URL, $this->wrongPayload())
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'too_many_requests');
    }

    public function test_throttle_is_keyed_per_staff_account(): void
    {
        $tokenA = $this->staff('a@carlton.test')->createToken('t')->plainTextToken;
        $tokenB = $this->staff('b@carlton.test')->createToken('t')->plainTextToken;

        for ($attempt = 1; $attempt <= self::LIMIT; $attempt++) {
            $this->withToken($tokenA)->putJson(self::URL, $this->wrongPayload())->assertStatus(422);
        }
        $this->withToken($tokenA)->putJson(self::URL, $this->wrongPayload())
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'too_many_requests');

        // Same test IP, different account: not locked out by A's attempts.
        $this->forgetGuards();
        $this->withToken($tokenB)->putJson(self::URL, $this->wrongPayload())
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_password_never_reaches_the_activity_log(): void
    {
        $user  = $this->staff();
        $token = $user->createToken('t')->plainTextToken;

        $response = $this->withToken($token)->putJson(self::URL, $this->validPayload())->assertOk();

        $rows = Activity::where('subject_type', User::class)->where('subject_id', $user->id)->get();

        // The factory-creation row exists, so the loop below checks something.
        $this->assertGreaterThanOrEqual(1, $rows->count());

        // spatie/laravel-activitylog v5 records model changes in
        // `attribute_changes`; `properties` holds custom data. Check both.
        foreach ($rows as $row) {
            foreach (['attribute_changes', 'properties'] as $column) {
                $logged = json_encode($row->{$column});
                $this->assertStringNotContainsString('"password"', $logged, "activity_log.{$column}");
                $this->assertStringNotContainsString('$2y$', $logged, "activity_log.{$column}");
            }
        }

        $this->assertStringNotContainsString(self::NEW_PASSWORD, $response->getContent());
        $this->assertStringNotContainsString('$2y$', $response->getContent());
    }

    public function test_password_change_messages_are_localized(): void
    {
        $en = $this->staff('en@carlton.test')->createToken('t')->plainTextToken;
        $this->withToken($en)->withHeader('Accept-Language', 'en')->putJson(self::URL, $this->validPayload())
            ->assertOk()
            ->assertJsonPath('message', 'Password changed.');

        $ar = $this->staff('ar@carlton.test')->createToken('t')->plainTextToken;
        $this->withToken($ar)->withHeader('Accept-Language', 'ar')->putJson(self::URL, $this->validPayload())
            ->assertOk()
            ->assertJsonPath('message', 'تم تغيير كلمة المرور.');

        $wrong = $this->staff('wrong@carlton.test')->createToken('t')->plainTextToken;
        $this->withToken($wrong)->withHeader('Accept-Language', 'en')->putJson(self::URL, $this->wrongPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

        $this->withToken($wrong)->withHeader('Accept-Language', 'ar')->putJson(self::URL, $this->wrongPayload())
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'كلمة المرور الحالية غير صحيحة.');
    }
}
