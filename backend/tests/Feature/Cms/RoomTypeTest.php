<?php

namespace Tests\Feature\Cms;

use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomTypeTest extends TestCase
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

    private function staffToken(): string
    {
        return User::factory()->create()->createToken('t')->plainTextToken;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'           => ['en' => 'Deluxe Suite', 'ar' => 'جناح ديلوكس'],
            'description'    => ['en' => 'Spacious suite', 'ar' => 'جناح واسع'],
            'amenities'      => ['WiFi', 'Pool'],
            'base_occupancy' => 2,
            'max_occupancy'  => 4,
            'size_sqm'       => 45.5,
            'base_price_usd' => 200.00,
            'is_active'      => true,
        ], $overrides);
    }

    // ── Admin CRUD ────────────────────────────────────────────────────────

    public function test_admin_can_list_room_types(): void
    {
        RoomType::factory()->count(3)->create();
        $this->withToken($this->editorToken())
            ->getJson('/api/cms/room-types')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['items', 'meta']]);
    }

    public function test_admin_can_create_room_type(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($res->json('data.uuid'));
        $this->assertSame(['en' => 'Deluxe Suite', 'ar' => 'جناح ديلوكس'], $res->json('data.name'));
    }

    public function test_admin_can_show_room_type(): void
    {
        $rt = RoomType::factory()->create();
        $this->withToken($this->editorToken())
            ->getJson("/api/cms/room-types/{$rt->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $rt->uuid);
    }

    public function test_admin_can_update_room_type(): void
    {
        $rt = RoomType::factory()->create();
        $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$rt->uuid}", ['name' => ['en' => 'Updated', 'ar' => 'محدث']])
            ->assertOk()
            ->assertJsonPath('data.name.en', 'Updated');
    }

    public function test_admin_can_delete_room_type(): void
    {
        $rt = RoomType::factory()->create();
        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/room-types/{$rt->uuid}")
            ->assertStatus(204);
        $this->assertDatabaseMissing('room_types', ['id' => $rt->id]);
    }

    // ── Permission gate ───────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/cms/room-types')->assertStatus(401);
        $this->postJson('/api/cms/room-types', [])->assertStatus(401);
    }

    public function test_staff_without_cms_edit_cannot_access_admin_endpoints(): void
    {
        $token = $this->staffToken();
        $this->withToken($token)->getJson('/api/cms/room-types')->assertStatus(403);
        $this->withToken($token)->postJson('/api/cms/room-types', $this->payload())->assertStatus(403);
    }

    // ── Public endpoints ──────────────────────────────────────────────────

    public function test_public_index_returns_only_active_room_types(): void
    {
        RoomType::factory()->count(2)->create(['is_active' => true]);
        RoomType::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/room-types')->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_public_show_returns_active_room_type_anonymously(): void
    {
        $rt = RoomType::factory()->create(['is_active' => true]);
        $this->getJson("/api/public/room-types/{$rt->uuid}")->assertOk();
    }

    public function test_public_show_inactive_room_type_returns_404(): void
    {
        $rt = RoomType::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/room-types/{$rt->uuid}")->assertNotFound();
    }

    // ── Locale switching ──────────────────────────────────────────────────

    public function test_arabic_locale_returns_arabic_content(): void
    {
        $rt = RoomType::factory()->create([
            'name' => ['en' => 'Suite', 'ar' => 'جناح'],
        ]);

        $res = $this->withHeaders(['Accept-Language' => 'ar'])
            ->getJson("/api/public/room-types/{$rt->uuid}")
            ->assertOk();

        // Resource always returns all translations; locale header affects app()->getLocale()
        $this->assertSame('جناح', $res->json('data.name.ar'));
    }

    // ── Image upload ──────────────────────────────────────────────────────

    public function test_admin_can_upload_image_to_room_type(): void
    {
        Storage::fake('public');
        $rt = RoomType::factory()->create();

        $res = $this->withToken($this->editorToken())
            ->postJson("/api/cms/room-types/{$rt->uuid}/images", [
                'image' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($res->json('data.url'));
        $this->assertDatabaseCount('media', 1);
    }

    public function test_admin_can_delete_image_from_room_type(): void
    {
        Storage::fake('public');
        $rt    = RoomType::factory()->create();
        $token = $this->editorToken();

        $res = $this->withToken($token)
            ->postJson("/api/cms/room-types/{$rt->uuid}/images", [
                'image' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertStatus(201);

        $mediaUuid = $res->json('data.uuid');
        $this->withToken($token)
            ->deleteJson("/api/cms/room-types/{$rt->uuid}/images/{$mediaUuid}")
            ->assertStatus(204);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_image_appears_in_room_type_resource(): void
    {
        Storage::fake('public');
        $rt    = RoomType::factory()->create();
        $token = $this->editorToken();

        $this->withToken($token)->postJson("/api/cms/room-types/{$rt->uuid}/images", [
            'image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $res = $this->withToken($token)
            ->getJson("/api/cms/room-types/{$rt->uuid}")
            ->assertOk();

        $this->assertCount(1, $res->json('data.images'));
        $this->assertNotEmpty($res->json('data.images.0.url'));
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_create_room_type_requires_bilingual_name(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', ['name' => ['en' => 'Only English']])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }
}
