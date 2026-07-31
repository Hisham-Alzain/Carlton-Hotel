<?php

namespace Tests\Feature\Cms;

use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The CMS shipped with cms.edit seeded but granted by no role preset, so the
 * only account that could reach /api/cms/* was the super admin — and only via
 * Gate::before, which left its advertised permission list empty. These tests
 * pin both halves of the fix.
 */
class CmsAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function tokenForRole(string $role): string
    {
        $user = User::factory()->staff()->create();
        $user->assignRole($role);

        return $user->createToken('t')->plainTextToken;
    }

    /** @param string|list<string> $permissions */
    private function tokenForPermissions(string|array $permissions): string
    {
        $user = User::factory()->staff()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    // ── cms.view actually grants read ─────────────────────────────────────
    //
    // cms.view was seeded and advertised as the read half of a read+write
    // pair, but the whole /api/cms block sat behind cms.edit, so the
    // permission granted nothing: every GET returned 403. An admin UI gating
    // navigation on the permissions array would have rendered links that then
    // 403'd. These tests pin read and write to separate permissions.

    public function test_cms_view_alone_grants_read_access(): void
    {
        $this->withToken($this->tokenForPermissions('cms.view'))
            ->getJson('/api/cms/room-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['items', 'meta']]);
    }

    public function test_cms_view_alone_grants_read_access_to_a_single_record(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->tokenForPermissions('cms.view'))
            ->getJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $roomType->uuid);
    }

    public function test_cms_view_alone_does_not_grant_write_access(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->tokenForPermissions('cms.view'))
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->assertDatabaseHas('room_types', ['id' => $roomType->id]);
    }

    /**
     * Reads are gated on `permission:cms.view|cms.edit`, which Spatie resolves
     * through canAny() — ANY, not ALL. An editor therefore reads without also
     * holding cms.view. If that pipe list is ever narrowed to bare cms.view,
     * every cms.edit-only account in the suite (and in production) loses read
     * access; this is the canary.
     */
    public function test_cms_edit_alone_still_grants_read_access(): void
    {
        $this->withToken($this->tokenForPermissions('cms.edit'))
            ->getJson('/api/cms/room-types')
            ->assertOk();
    }

    public function test_content_editor_role_holds_both_halves_of_the_contract(): void
    {
        $token = $this->tokenForRole('content_editor');

        $this->withToken($token)->getJson('/api/cms/room-types')->assertOk();

        $roomType = RoomType::factory()->create();
        $this->withToken($token)
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(204);
    }

    /**
     * Guards the seeded-vs-enforced contract in general, not just for cms.view.
     *
     * A permission that no route middleware gates and that no policy or service
     * names is inert: seeding it advertises a capability the API never checks.
     * That is exactly how cms.view shipped — present in the seeder, present in
     * no route, gate, policy or controller.
     */
    public function test_every_seeded_permission_is_enforced_somewhere(): void
    {
        // Seeded ahead of the phases that will consume them (pricing admin,
        // reporting). Both are role-less today; remove from this list as soon
        // as the endpoint that enforces them lands.
        $notYetBuilt = ['pricing.edit', 'reports.view'];

        $enforcedByRoutes = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($m) => is_string($m))
            // Matches the alias form ("permission:a|b") and the resolved class
            // form ("Spatie\...\PermissionMiddleware:a|b") — route middleware is
            // reported as either depending on how it was registered.
            ->map(fn ($m) => preg_match('/(?:^permission|PermissionMiddleware):(.+)$/', $m, $hit) ? $hit[1] : null)
            ->filter()
            // Strip the optional trailing ",guard" argument, then split the
            // pipe-separated "any of these" list.
            ->flatMap(fn ($arg) => explode('|', explode(',', $arg)[0]))
            ->map(fn ($p) => trim($p))
            ->unique();

        // Some permissions are enforced below the routing layer — StaffPolicy
        // checks staff.manage, OperationsQueueService resolves the
        // service_requests.* permission per operation. Scanning app/ for the
        // literal keeps those honest without hardcoding a stale allowlist.
        $appSource = collect(
            iterator_to_array(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
                ),
                false
            )
        )
            ->filter(fn (\SplFileInfo $f) => $f->isFile() && $f->getExtension() === 'php')
            ->map(fn (\SplFileInfo $f) => (string) file_get_contents($f->getPathname()))
            ->implode("\n");

        $inert = Permission::where('guard_name', 'users')->pluck('name')
            ->reject(fn ($p) => $enforcedByRoutes->contains($p))
            ->reject(fn ($p) => str_contains($appSource, "'{$p}'") || str_contains($appSource, "\"{$p}\""))
            ->reject(fn ($p) => in_array($p, $notYetBuilt, true))
            ->values();

        $this->assertSame(
            [],
            $inert->all(),
            'Seeded permissions that no route, policy or service enforces (they grant nothing): '.$inert->implode(', '),
        );
    }

    /**
     * cms.view is enforced by route middleware specifically — not merely
     * mentioned somewhere in app/. Pins the route-level gate that the broader
     * guard above would also accept a stray string literal for.
     */
    public function test_cms_view_is_enforced_by_route_middleware(): void
    {
        $gated = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/cms'))
            ->filter(fn ($route) => collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m)
                    && preg_match('/(?:^permission|PermissionMiddleware):/', $m)
                    && str_contains($m, 'cms.view')));

        $this->assertNotEmpty($gated, 'No /api/cms route enforces cms.view — the permission grants nothing.');
    }

    // ── Guard memoization ─────────────────────────────────────────────────

    /**
     * Laravel memoizes the resolved user on the guard for the lifetime of one
     * test, so a second withToken() with a different identity used to be served
     * the first user — this test would report 200 for an account that must be
     * forbidden, i.e. a green test proving nothing. Tests\TestCase::withToken()
     * forgets the guards on every call; this is the regression pin for that.
     */
    public function test_a_second_identity_in_one_test_is_not_served_the_first_users_guard(): void
    {
        $editorToken   = $this->tokenForRole('content_editor');
        $outsiderToken = $this->tokenForRole('reception');

        $this->withToken($editorToken)->getJson('/api/cms/room-types')->assertOk();

        $this->withToken($outsiderToken)
            ->getJson('/api/cms/room-types')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_switching_from_a_forbidden_to_an_allowed_identity_also_re_resolves(): void
    {
        $outsiderToken = $this->tokenForRole('reception');
        $editorToken   = $this->tokenForRole('content_editor');

        $this->withToken($outsiderToken)->getJson('/api/cms/room-types')->assertStatus(403);

        $this->withToken($editorToken)->getJson('/api/cms/room-types')->assertOk();
    }

    // ── Role preset reaches the CMS ───────────────────────────────────────

    public function test_content_editor_role_can_reach_a_cms_endpoint(): void
    {
        $this->withToken($this->tokenForRole('content_editor'))
            ->getJson('/api/cms/room-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['items', 'meta']]);
    }

    public function test_content_editor_role_can_write_through_the_cms(): void
    {
        // Delete rather than create: proves the write gate without coupling
        // this test to any Cms FormRequest's payload rules.
        $roomType = RoomType::factory()->create();

        $this->withToken($this->tokenForRole('content_editor'))
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(204);

        // Recoverable now: the row stays, marked, and vanishes from every query.
        $this->assertSoftDeleted('room_types', ['id' => $roomType->id]);
    }

    public function test_reception_role_cannot_write_through_the_cms(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->tokenForRole('reception'))
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(403);

        $this->assertDatabaseHas('room_types', ['id' => $roomType->id]);
    }

    public function test_reception_role_is_forbidden_from_the_cms(): void
    {
        $this->withToken($this->tokenForRole('reception'))
            ->getJson('/api/cms/room-types')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_unauthenticated_request_to_the_cms_is_unauthorized(): void
    {
        $this->getJson('/api/cms/room-types')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    // ── Super admin advertises its CMS capability ─────────────────────────

    public function test_super_admin_login_exposes_cms_capability(): void
    {
        User::factory()->superAdmin()->create([
            'email'    => 'boss@carlton.demo',
            'password' => bcrypt('secret'),
        ]);

        $res = $this->postJson('/api/auth/login', [
            'email'    => 'boss@carlton.demo',
            'password' => 'secret',
        ])->assertOk();

        $this->assertTrue($res->json('data.user.is_super_admin'));
        $this->assertContains('cms.edit', $res->json('data.user.permissions'));
        // Top-level mirror consumed by the dashboard's permission store.
        $this->assertContains('cms.edit', $res->json('data.permissions'));
    }

    public function test_super_admin_profile_exposes_cms_capability(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $res = $this->withToken($admin->createToken('t')->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertTrue($res->json('data.is_super_admin'));
        $this->assertContains('cms.edit', $res->json('data.permissions'));
        $this->assertContains('staff.manage', $res->json('data.permissions'));
    }

    public function test_super_admin_permission_list_matches_what_the_api_allows(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/auth/me')->assertOk();

        // Advertised capability and actual access must agree.
        $this->assertContains('cms.edit', $res->json('data.permissions'));
        $this->withToken($token)->getJson('/api/cms/room-types')->assertOk();
    }

    // ── Non-super-admins keep the assigned-permission semantics ───────────

    public function test_non_super_admin_reports_only_its_own_permissions(): void
    {
        $user = User::factory()->staff()->create();
        $user->assignRole('content_editor');

        $res = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertFalse($res->json('data.is_super_admin'));
        $this->assertSame(
            ['cms.edit', 'cms.restore', 'cms.view'],
            collect($res->json('data.permissions'))->sort()->values()->all(),
        );
        $this->assertNotContains('staff.manage', $res->json('data.permissions'));
        // The preset stops short of the bin's destructive half; content_manager
        // is the preset that carries it.
        $this->assertNotContains('cms.purge', $res->json('data.permissions'));
    }

    public function test_role_less_staff_reports_no_permissions(): void
    {
        $user = User::factory()->staff()->create();

        $res = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertFalse($res->json('data.is_super_admin'));
        $this->assertSame([], $res->json('data.permissions'));
    }
}
