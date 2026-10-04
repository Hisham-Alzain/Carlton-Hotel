<?php

namespace Tests\Feature\Loyalty;

use App\Models\Guest;
use App\Models\LoyaltySetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 plan 04 (LOY-01, LOY-02, LOY-22; Q4, M-8): the staff program
 * settings endpoint. GET reads the singleton (nulls and defaults when staff
 * have not configured anything, writing nothing); PUT applies only the keys
 * present, audited.
 */
class LoyaltySettingsTest extends TestCase
{
    use BuildsLoyaltyFixtures, RefreshDatabase;

    private const URL = '/api/cms/loyalty/settings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function get_(?string $token, array $headers = ['Accept-Language' => 'en']): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return ($token === null ? $this : $this->withToken($token))->getJson(self::URL, $headers);
    }

    private function put_(?string $token, array $body, array $headers = ['Accept-Language' => 'en']): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return ($token === null ? $this : $this->withToken($token))->putJson(self::URL, $body, $headers);
    }

    /** @return array{0: User, 1: string} */
    private function manager(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo('loyalty.manage');

        return [$user, $user->createToken('t')->plainTextToken];
    }

    private function fullBody(): array
    {
        return [
            'earn_rate' => '1.25',
            'redeem_value_usd' => '0.0100',
            'expiry_months' => 12,
            'expiry_warning_days' => 14,
            'min_redeem_points' => 200,
            'max_redeem_percent' => '50',
        ];
    }

    // ── GET ──────────────────────────────────────────────────────────────

    public function test_get_with_no_row_returns_nulls_and_defaults_and_writes_nothing(): void
    {
        foreach (['loyalty.view', 'loyalty.manage'] as $permission) {
            $this->get_($this->staffToken($permission))
                ->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.earn_rate', null)
                ->assertJsonPath('data.redeem_value_usd', null)
                ->assertJsonPath('data.expiry_months', 24)
                ->assertJsonPath('data.expiry_warning_days', 30)
                ->assertJsonPath('data.min_redeem_points', null)
                ->assertJsonPath('data.max_redeem_percent', null)
                ->assertJsonPath('data.program', ['earning' => false, 'points_discount' => false, 'rewards' => true])
                ->assertJsonPath('data.updated_at', null);
        }

        $this->assertSame(0, DB::table('loyalty_settings')->count());
    }

    public function test_get_returns_the_stored_values(): void
    {
        $this->configureLoyalty([
            'earn_rate' => '1.5000', 'redeem_value_usd' => '0.0200', 'expiry_months' => 18,
            'expiry_warning_days' => 21, 'min_redeem_points' => 50, 'max_redeem_percent' => '25.00',
        ]);

        $this->get_($this->staffToken('loyalty.view'))
            ->assertStatus(200)
            ->assertJsonPath('data.earn_rate', '1.5000')
            ->assertJsonPath('data.redeem_value_usd', '0.0200')
            ->assertJsonPath('data.expiry_months', 18)
            ->assertJsonPath('data.expiry_warning_days', 21)
            ->assertJsonPath('data.min_redeem_points', 50)
            ->assertJsonPath('data.max_redeem_percent', '25.00')
            ->assertJsonPath('data.program', ['earning' => true, 'points_discount' => true, 'rewards' => true])
            ->assertJsonStructure(['data' => ['updated_at']]);
    }

    // ── PUT ──────────────────────────────────────────────────────────────

    public function test_put_creates_the_singleton_row_and_answers_with_the_new_values(): void
    {
        [$user, $token] = $this->manager();

        $this->put_($token, $this->fullBody())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('custom.messages.loyalty_settings_updated', [], 'en'))
            ->assertJsonPath('data.earn_rate', '1.2500')
            ->assertJsonPath('data.redeem_value_usd', '0.0100')
            ->assertJsonPath('data.expiry_months', 12)
            ->assertJsonPath('data.expiry_warning_days', 14)
            ->assertJsonPath('data.min_redeem_points', 200)
            ->assertJsonPath('data.max_redeem_percent', '50.00')
            ->assertJsonPath('data.program', ['earning' => true, 'points_discount' => true, 'rewards' => true]);

        $this->assertSame(1, LoyaltySetting::count());
        $row = LoyaltySetting::firstOrFail();
        $this->assertSame(1, (int) $row->singleton);
        $this->assertEquals($user->id, $row->updated_by);
    }

    public function test_put_is_recorded_in_the_activity_log_with_old_and_new_values(): void
    {
        $this->configureLoyalty(['earn_rate' => '1.0000']);
        [, $token] = $this->manager();

        $this->put_($token, ['earn_rate' => '2'])->assertStatus(200);

        $row = Activity::where('subject_type', LoyaltySetting::class)->where('event', 'updated')->latest('id')->first();
        $this->assertNotNull($row, 'an updated activity row exists for the settings subject');

        $logged = json_encode([$row->attribute_changes, $row->properties]);
        $this->assertStringContainsString('earn_rate', $logged);
        $this->assertMatchesRegularExpression('/"old":\{[^}]*"earn_rate":"?1(\.0+)?"?/', $logged, 'old value logged');
        $this->assertMatchesRegularExpression('/"attributes":\{[^}]*"earn_rate":"?2(\.0+)?"?/', $logged, 'new value logged');
    }

    public function test_put_applies_only_the_keys_present_and_an_explicit_null_clears(): void
    {
        [, $token] = $this->manager();
        $this->put_($token, $this->fullBody())->assertStatus(200);

        $this->put_($token, ['max_redeem_percent' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.max_redeem_percent', null)
            ->assertJsonPath('data.earn_rate', '1.2500')
            ->assertJsonPath('data.redeem_value_usd', '0.0100')
            ->assertJsonPath('data.expiry_months', 12)
            ->assertJsonPath('data.expiry_warning_days', 14)
            ->assertJsonPath('data.min_redeem_points', 200)
            ->assertJsonPath('data.program', ['earning' => true, 'points_discount' => false, 'rewards' => true]);

        $this->assertSame(1, LoyaltySetting::count());
    }

    public function test_an_empty_put_returns_200_with_unchanged_values(): void
    {
        [, $token] = $this->manager();
        $this->put_($token, $this->fullBody())->assertStatus(200);

        $this->put_($token, [])
            ->assertStatus(200)
            ->assertJsonPath('data.earn_rate', '1.2500')
            ->assertJsonPath('data.expiry_months', 12)
            ->assertJsonPath('data.max_redeem_percent', '50.00');

        $this->assertSame(1, LoyaltySetting::count());
    }

    // ── LOY-22: capabilities ─────────────────────────────────────────────

    public function test_a_redeem_value_without_a_cap_leaves_points_discount_off(): void
    {
        [, $token] = $this->manager();

        $this->put_($token, ['redeem_value_usd' => '0.0100', 'max_redeem_percent' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.program.points_discount', false)
            ->assertJsonPath('data.program.rewards', true);
    }

    public function test_an_earn_rate_of_zero_leaves_earning_off(): void
    {
        [, $token] = $this->manager();

        $this->put_($token, ['earn_rate' => '0'])
            ->assertStatus(200)
            ->assertJsonPath('data.earn_rate', '0.0000')
            ->assertJsonPath('data.program.earning', false);
    }

    // ── 401 / 403 ────────────────────────────────────────────────────────

    public function test_unauthenticated_and_guest_tokens_get_401(): void
    {
        $this->get_(null)->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->put_(null, $this->fullBody())->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');

        $guestToken = $this->guestToken(Guest::factory()->create());
        $this->get_($guestToken)->assertStatus(401);
        $this->put_($guestToken, $this->fullBody())->assertStatus(401);

        $this->assertSame(0, LoyaltySetting::count());
    }

    public function test_users_without_the_right_permission_get_403(): void
    {
        $this->get_($this->staffToken())->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->get_($this->staffToken('reports.view'))->assertStatus(403);
        $this->put_($this->staffToken('loyalty.view'), $this->fullBody())->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->put_($this->staffToken('loyalty.adjust'), $this->fullBody())->assertStatus(403);
        $this->put_($this->presetToken('reception'), $this->fullBody())->assertStatus(403);

        $this->assertSame(0, LoyaltySetting::count());
    }

    // ── 422 ──────────────────────────────────────────────────────────────

    public function test_invalid_rates_are_rejected_with_422(): void
    {
        [, $token] = $this->manager();

        $this->put_($token, ['earn_rate' => -1])->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonStructure(['errors' => ['earn_rate']]);
        $this->put_($token, ['earn_rate' => 'abc'])->assertStatus(422)->assertJsonStructure(['errors' => ['earn_rate']]);
        $this->put_($token, ['earn_rate' => '1.12345'])->assertStatus(422)->assertJsonStructure(['errors' => ['earn_rate']]);
        $this->put_($token, ['earn_rate' => '10000'])->assertStatus(422)->assertJsonStructure(['errors' => ['earn_rate']]);
        $this->put_($token, ['redeem_value_usd' => -0.01])->assertStatus(422)->assertJsonStructure(['errors' => ['redeem_value_usd']]);
        $this->put_($token, ['redeem_value_usd' => '1000000'])->assertStatus(422)->assertJsonStructure(['errors' => ['redeem_value_usd']]);
        $this->put_($token, ['redeem_value_usd' => '0.00001'])->assertStatus(422)->assertJsonStructure(['errors' => ['redeem_value_usd']]);

        $this->assertSame(0, LoyaltySetting::count());
    }

    public function test_invalid_windows_and_minimums_are_rejected_with_422(): void
    {
        [, $token] = $this->manager();

        $this->put_($token, ['expiry_months' => 0])->assertStatus(422)->assertJsonStructure(['errors' => ['expiry_months']]);
        $this->put_($token, ['expiry_months' => 121])->assertStatus(422)->assertJsonStructure(['errors' => ['expiry_months']]);
        $this->put_($token, ['expiry_months' => null])->assertStatus(422)->assertJsonStructure(['errors' => ['expiry_months']]);
        $this->put_($token, ['expiry_months' => 1.5])->assertStatus(422)->assertJsonStructure(['errors' => ['expiry_months']]);
        $this->put_($token, ['expiry_warning_days' => 0])->assertStatus(422)->assertJsonStructure(['errors' => ['expiry_warning_days']]);
        $this->put_($token, ['expiry_warning_days' => 366])->assertStatus(422)->assertJsonStructure(['errors' => ['expiry_warning_days']]);
        $this->put_($token, ['min_redeem_points' => 0])->assertStatus(422)->assertJsonStructure(['errors' => ['min_redeem_points']]);
        $this->put_($token, ['min_redeem_points' => 100000001])->assertStatus(422)->assertJsonStructure(['errors' => ['min_redeem_points']]);

        $this->assertSame(0, LoyaltySetting::count());
    }

    public function test_an_invalid_cap_is_rejected_with_422(): void
    {
        [, $token] = $this->manager();

        $this->put_($token, ['max_redeem_percent' => 0])->assertStatus(422)->assertJsonStructure(['errors' => ['max_redeem_percent']]);
        $this->put_($token, ['max_redeem_percent' => '100.01'])->assertStatus(422)->assertJsonStructure(['errors' => ['max_redeem_percent']]);
        $this->put_($token, ['max_redeem_percent' => '10.005'])->assertStatus(422)->assertJsonStructure(['errors' => ['max_redeem_percent']]);
        $this->put_($token, ['max_redeem_percent' => -5])->assertStatus(422)->assertJsonStructure(['errors' => ['max_redeem_percent']]);

        $this->assertSame(0, LoyaltySetting::count());
    }

    public function test_boundary_values_are_accepted(): void
    {
        [, $token] = $this->manager();

        $this->put_($token, [
            'earn_rate' => '9999.9999', 'redeem_value_usd' => '999999.9999', 'expiry_months' => 120,
            'expiry_warning_days' => 365, 'min_redeem_points' => 100000000, 'max_redeem_percent' => '100',
        ])->assertStatus(200);

        $this->put_($token, [
            'earn_rate' => null, 'redeem_value_usd' => '0', 'expiry_months' => 1,
            'expiry_warning_days' => 1, 'min_redeem_points' => 1, 'max_redeem_percent' => '0.01',
        ])->assertStatus(200)->assertJsonPath('data.earn_rate', null);
    }

    public function test_a_422_is_localized_in_arabic(): void
    {
        [, $token] = $this->manager();

        $response = $this->put_($token, ['earn_rate' => -1], ['Accept-Language' => 'ar'])
            ->assertStatus(422);

        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $response->json('message'));
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $response->json('errors.earn_rate.0'));
    }

    // ── M-8 ──────────────────────────────────────────────────────────────

    public function test_the_public_site_settings_table_is_untouched(): void
    {
        [, $token] = $this->manager();
        $before = DB::table('site_settings')->count();

        $this->put_($token, $this->fullBody())->assertStatus(200);

        $this->assertSame($before, DB::table('site_settings')->count());
    }
}
