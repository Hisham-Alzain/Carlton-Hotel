<?php

namespace Tests\Feature\Cms;

use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertDatabaseMissing('room_types', ['id' => $roomType->id]);
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
            ['cms.edit', 'cms.view'],
            collect($res->json('data.permissions'))->sort()->values()->all(),
        );
        $this->assertNotContains('staff.manage', $res->json('data.permissions'));
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
