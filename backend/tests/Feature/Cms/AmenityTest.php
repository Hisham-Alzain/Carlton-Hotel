<?php

namespace Tests\Feature\Cms;

use App\Models\Amenity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmenityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');
        return $user->createToken('t')->plainTextToken;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'       => ['en' => 'Smart TV', 'ar' => 'تلفاز ذكي'],
            'icon'       => 'tv',
            'is_active'  => true,
            'sort_order' => 3,
        ], $overrides);
    }

    // ── Admin CRUD ────────────────────────────────────────────────────────

    public function test_admin_can_create_amenity_with_slug_derived_from_name(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/amenities', $this->payload())
            ->assertStatus(201);

        $this->assertSame('smart-tv', $res->json('data.slug'));
        $this->assertSame('تلفاز ذكي', $res->json('data.name.ar'));
    }

    public function test_admin_can_list_show_update_and_delete_amenities(): void
    {
        $token   = $this->editorToken();
        $amenity = Amenity::factory()->create();

        $this->withToken($token)->getJson('/api/cms/amenities')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['items', 'meta']]);

        $this->withToken($token)->getJson("/api/cms/amenities/{$amenity->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $amenity->uuid);

        $this->withToken($token)
            ->putJson("/api/cms/amenities/{$amenity->uuid}", ['name' => ['en' => 'Jacuzzi', 'ar' => 'جاكوزي']])
            ->assertOk()
            ->assertJsonPath('data.name.en', 'Jacuzzi');

        $this->withToken($token)->deleteJson("/api/cms/amenities/{$amenity->uuid}")->assertStatus(204);
        // Recoverable now: the row stays, marked, and vanishes from every query.
        $this->assertSoftDeleted('amenities', ['id' => $amenity->id]);
    }

    public function test_slug_must_be_unique(): void
    {
        Amenity::factory()->create(['slug' => 'smart-tv']);

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/amenities', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_create_requires_bilingual_name(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/amenities', ['name' => ['en' => 'Only English']])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    // ── Permission gate ───────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/cms/amenities')->assertStatus(401);
        $this->postJson('/api/cms/amenities', [])->assertStatus(401);
    }

    public function test_staff_without_cms_edit_cannot_write(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/amenities', $this->payload())->assertStatus(403);
    }

    // ── Public endpoint ───────────────────────────────────────────────────

    public function test_public_index_returns_only_active_amenities(): void
    {
        Amenity::factory()->count(2)->create();
        Amenity::factory()->inactive()->create();

        $res = $this->getJson('/api/public/amenities')->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }
}
