<?php

namespace Tests\Feature\Auth;

use App\Models\Guest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * ACCESS-02: `GET /api/auth/profile` and `PUT /api/auth/profile`
 * (D-01, D-02, D-03, D-06, D-11, D-12).
 *
 * Real bearer tokens only; no acting-as helper.
 */
class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private const URL      = '/api/auth/profile';
    private const PASSWORD = 'Original-pass-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array{0: User, 1: string} */
    private function staff(array $attributes = []): array
    {
        $user = User::factory()->create(array_merge([
            'name'     => 'Front Desk',
            'email'    => 'a.b@carlton.test',
            'password' => self::PASSWORD,
        ], $attributes));

        return [$user, $user->createToken('t')->plainTextToken];
    }

    public function test_staff_can_view_own_profile(): void
    {
        [$user, $token] = $this->staff();
        $user->assignRole('reception');

        $profile = $this->withToken($token)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $user->uuid);

        $this->assertEqualsCanonicalizing(
            ['uuid', 'name', 'email', 'type', 'is_active', 'is_super_admin', 'roles', 'permissions'],
            array_keys($profile->json('data'))
        );
        $this->assertArrayNotHasKey('id', $profile->json('data'));

        $me = $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        $this->assertSame($me->json('data'), $profile->json('data'));
    }

    public function test_staff_can_update_own_name(): void
    {
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, ['name' => 'Night Manager'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.name', 'Night Manager')
            ->assertJsonPath('data.email', 'a.b@carlton.test');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Night Manager', 'email' => 'a.b@carlton.test']);
    }

    public function test_staff_can_change_email_with_correct_current_password(): void
    {
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, [
            'email'            => 'new.address@carlton.test',
            'current_password' => self::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'new.address@carlton.test');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new.address@carlton.test']);
        // current_password never reaches the model.
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_email_change_without_current_password_returns_422(): void
    {
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, ['email' => 'new.address@carlton.test'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'a.b@carlton.test']);
    }

    public function test_email_change_with_wrong_current_password_returns_422(): void
    {
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, [
            'name'             => 'Hijacked Name',
            'email'            => 'new.address@carlton.test',
            'current_password' => 'Not-the-password-9',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Front Desk', 'email' => 'a.b@carlton.test']);
    }

    public function test_case_only_email_change_requires_current_password(): void
    {
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, ['email' => 'A.B@carlton.test'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['current_password']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'a.b@carlton.test']);
    }

    public function test_same_email_does_not_require_current_password(): void
    {
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, ['name' => 'Renamed', 'email' => 'a.b@carlton.test'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.email', 'a.b@carlton.test');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Renamed']);
    }

    public function test_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@carlton.test']);
        [$user, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, [
            'email'            => 'taken@carlton.test',
            'current_password' => self::PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['email']]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'a.b@carlton.test']);
    }

    public function test_empty_body_returns_422(): void
    {
        [, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['name', 'email']]);
    }

    public function test_profile_update_ignores_privileged_fields(): void
    {
        [$user, $token] = $this->staff(['type' => 'staff']);
        $user->assignRole('reception');

        $this->withToken($token)->putJson(self::URL, [
            'name'        => 'Still Reception',
            'type'        => 'super_admin',
            'is_active'   => false,
            'roles'       => ['super_admin'],
            'permissions' => ['staff.manage'],
            'password'    => 'Hijacked-pass-9',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Still Reception')
            ->assertJsonPath('data.type', 'staff')
            ->assertJsonPath('data.is_super_admin', false);

        $fresh = $user->fresh();
        $this->assertSame('staff', $fresh->type);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame(['reception'], $fresh->getRoleNames()->all());
        $this->assertCount(0, $fresh->getDirectPermissions());
        $this->assertTrue(Hash::check(self::PASSWORD, $fresh->password));
    }

    public function test_arabic_name_round_trips_and_length_counts_characters(): void
    {
        [, $token] = $this->staff();

        $this->withToken($token)->putJson(self::URL, ['name' => 'هشام الزين'])->assertOk();
        $this->withToken($token)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.name', 'هشام الزين');

        // 255 characters (510 bytes) passes: max counts characters, not bytes.
        $this->withToken($token)->putJson(self::URL, ['name' => str_repeat('ه', 255)])
            ->assertOk()
            ->assertJsonPath('data.name', str_repeat('ه', 255));

        $this->withToken($token)->putJson(self::URL, ['name' => str_repeat('ه', 256)])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_repeating_identical_update_is_a_no_op(): void
    {
        [, $token] = $this->staff();

        $first      = $this->withToken($token)->putJson(self::URL, ['name' => 'Front Desk Lead'])->assertOk();
        $afterFirst = Activity::count();

        $second = $this->withToken($token)->putJson(self::URL, ['name' => 'Front Desk Lead'])->assertOk();

        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame($afterFirst, Activity::count());
    }

    public function test_current_password_error_is_localized(): void
    {
        [, $token] = $this->staff();
        $payload   = ['email' => 'new.address@carlton.test', 'current_password' => 'Not-the-password-9'];

        $this->withToken($token)->withHeader('Accept-Language', 'en')->putJson(self::URL, $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

        $this->withToken($token)->withHeader('Accept-Language', 'ar')->putJson(self::URL, $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'كلمة المرور الحالية غير صحيحة.');
    }

    public function test_unauthenticated_cannot_access_profile(): void
    {
        $this->getJson(self::URL)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->putJson(self::URL, ['name' => 'Nobody'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_guest_token_is_rejected_on_profile_routes(): void
    {
        $guestToken = Guest::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($guestToken)->getJson(self::URL)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->app->get('auth')->forgetGuards();
        $this->withToken($guestToken)->putJson(self::URL, ['name' => 'Guest Takeover'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_profile_update_is_not_throttled(): void
    {
        [$user, $token] = $this->staff();

        for ($i = 1; $i <= 7; $i++) {
            $this->withToken($token)->putJson(self::URL, ['name' => "Name {$i}"])
                ->assertOk()
                ->assertJsonPath('data.name', "Name {$i}");
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Name 7']);
    }
}
