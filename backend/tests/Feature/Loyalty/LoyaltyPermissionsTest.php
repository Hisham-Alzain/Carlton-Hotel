<?php

namespace Tests\Feature\Loyalty;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * Phase 10 (LOY-20, Q7): the three loyalty permissions, their grouping, and
 * the route-gate contract every present and future loyalty route is checked
 * against. The route checks are dynamic (they walk the router), so later
 * plans' routes are covered without editing this file.
 */
class LoyaltyPermissionsTest extends TestCase
{
    use BuildsLoyaltyFixtures, RefreshDatabase;

    private const LOYALTY_PERMISSIONS = ['loyalty.adjust', 'loyalty.manage', 'loyalty.view'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function settingsAs(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/cms/loyalty/settings');
    }

    public function test_the_three_permissions_are_seeded_under_the_users_guard(): void
    {
        foreach (self::LOYALTY_PERMISSIONS as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'users']);
        }
        $this->assertSame(3, Permission::where('guard_name', 'users')->where('name', 'like', 'loyalty.%')->count());
    }

    public function test_no_seeded_role_holds_a_loyalty_permission(): void
    {
        $roles = Role::where('guard_name', 'users')->get();
        $this->assertNotEmpty($roles);

        foreach ($roles as $role) {
            $loyalty = $role->permissions->pluck('name')->filter(fn ($n) => str_starts_with($n, 'loyalty.'))->values()->all();
            $this->assertSame([], $loyalty, "role {$role->name} must hold no loyalty.* permission");
        }
    }

    public function test_the_permission_catalogue_groups_loyalty_as_one_module_of_three(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('staff.manage');

        $this->app['auth']->forgetGuards();
        $groups = $this->withToken($actor->createToken('t')->plainTextToken)
            ->getJson('/api/permissions')
            ->assertStatus(200)
            ->json('data');

        $loyalty = collect($groups)->firstWhere('module', 'loyalty');
        $this->assertNotNull($loyalty, 'loyalty module exists');
        $this->assertSame(self::LOYALTY_PERMISSIONS, collect($loyalty['permissions'])->sort()->values()->all());
    }

    public function test_every_preset_role_and_a_reports_only_user_get_403_and_super_admin_gets_200(): void
    {
        foreach (['reception', 'kitchen', 'housekeeping', 'concierge', 'events', 'content_editor', 'content_manager'] as $role) {
            $this->settingsAs($this->presetToken($role))
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'forbidden');
        }

        $this->settingsAs($this->staffToken('reports.view'))->assertStatus(403);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->settingsAs($superAdmin->createToken('t')->plainTextToken)
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    private function reportsAs(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/cms/loyalty/reports');
    }

    public function test_the_loyalty_report_is_403_for_every_preset_and_for_reports_view_or_manage_only(): void
    {
        foreach (['reception', 'kitchen', 'housekeeping', 'concierge', 'events', 'content_editor', 'content_manager'] as $role) {
            $this->reportsAs($this->presetToken($role))
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'forbidden');
        }

        // The revenue report permission does not open the loyalty report (Q7, T-10-55).
        $this->reportsAs($this->staffToken('reports.view'))->assertStatus(403);
        // loyalty.manage alone is not enough: the report is a loyalty.view read.
        $this->reportsAs($this->staffToken('loyalty.manage'))->assertStatus(403);
        $this->reportsAs($this->staffToken('loyalty.adjust'))->assertStatus(403);

        $this->reportsAs($this->staffToken('loyalty.view'))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $superAdmin = User::factory()->superAdmin()->create();
        $this->reportsAs($superAdmin->createToken('t')->plainTextToken)->assertStatus(200);
    }

    public function test_every_staff_loyalty_route_is_behind_auth_users_and_a_loyalty_or_bin_permission(): void
    {
        $allowed = ['loyalty.view', 'loyalty.manage', 'loyalty.adjust', 'cms.restore', 'cms.purge'];
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/cms/loyalty'));

        $this->assertNotEmpty($routes, 'the api/cms/loyalty block exists');

        foreach ($routes as $route) {
            $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m));
            $label = implode('|', $route->methods()).' '.$route->uri();

            $this->assertTrue($middleware->contains('auth:users'), "{$label} lacks auth:users");

            $named = $middleware
                ->map(fn ($m) => preg_match('/(?:^permission|PermissionMiddleware):(.+)$/', $m, $hit) ? explode('|', $hit[1]) : null)
                ->filter()
                ->flatten();

            $this->assertNotEmpty(
                $named->intersect($allowed)->all(),
                "{$label} has no permission middleware naming one of: ".implode(', ', $allowed),
            );
        }
    }

    public function test_every_guest_loyalty_route_is_behind_auth_guests(): void
    {
        // Legitimately empty until plan 10-06 adds the guest block; applies to
        // every guest route that exists from then on.
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/loyalty'));

        foreach ($routes as $route) {
            $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => is_string($m));
            $this->assertTrue(
                $middleware->contains('auth:guests'),
                implode('|', $route->methods()).' '.$route->uri().' lacks auth:guests',
            );
        }

        $this->assertTrue(true);
    }
}
