<?php

namespace Tests\Feature\Cms;

use App\Enums\BedType;
use App\Enums\RoomView;
use App\Models\Amenity;
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
            'view_type'      => RoomView::CITY->value,
            'bed_types'      => [BedType::KING->value, BedType::EXTRA->value],
            'base_occupancy' => 2,
            'max_occupancy'  => 4,
            'size_sqm'       => 45.5,
            'base_price_usd' => 200.00,
            'cancellation_hours' => 48,
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
        // Recoverable now: the row stays, marked, and vanishes from every query.
        $this->assertSoftDeleted('room_types', ['id' => $rt->id]);
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

    // ── Mobile fields: view, beds, cancellation, amenities ────────────────

    public function test_create_persists_view_bed_types_and_cancellation_window(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->payload())
            ->assertStatus(201);

        $this->assertSame(RoomView::CITY->value, $res->json('data.view_type'));
        $this->assertSame([BedType::KING->value, BedType::EXTRA->value], $res->json('data.bed_types'));
        $this->assertSame(48, $res->json('data.cancellation_hours'));
    }

    public function test_create_rejects_unknown_bed_type(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->payload(['bed_types' => ['bunk']]))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_admin_can_attach_amenities_and_flag_highlights(): void
    {
        $amenities = Amenity::factory()->count(6)->create();

        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types', $this->payload([
                'amenities' => $amenities->values()->map(fn ($a, $i) => [
                    'uuid'         => $a->uuid,
                    'is_highlight' => $i < 2,
                    'sort_order'   => $i,
                ])->all(),
            ]))
            ->assertStatus(201);

        $this->assertCount(6, $res->json('data.amenities'));
        // Two flagged highlights, topped up to four from the head of the list.
        $highlights = $res->json('data.highlights');
        $this->assertCount(4, $highlights);
        $this->assertSame($amenities[0]->uuid, $highlights[0]['uuid']);
        $this->assertSame($amenities[1]->uuid, $highlights[1]['uuid']);
    }

    public function test_highlights_fall_back_to_first_four_when_none_flagged(): void
    {
        $amenities = Amenity::factory()->count(5)->create();
        $roomType  = RoomType::factory()->create();
        $roomType->amenityList()->attach(
            $amenities->values()->mapWithKeys(fn ($a, $i) => [
                $a->id => ['is_highlight' => false, 'sort_order' => $i],
            ])->all(),
        );

        $res = $this->getJson("/api/public/room-types/{$roomType->uuid}")->assertOk();

        $this->assertCount(4, $res->json('data.highlights'));
        $this->assertSame($amenities[0]->uuid, $res->json('data.highlights.0.uuid'));
    }

    public function test_update_without_amenities_key_leaves_pivot_untouched(): void
    {
        $amenities = Amenity::factory()->count(3)->create();
        $roomType  = RoomType::factory()->create();
        $roomType->amenityList()->attach($amenities->pluck('id')->all());

        $res = $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['base_price_usd' => 999])
            ->assertOk();

        $this->assertCount(3, $res->json('data.amenities'));
    }

    public function test_update_with_empty_amenities_clears_pivot(): void
    {
        $amenities = Amenity::factory()->count(3)->create();
        $roomType  = RoomType::factory()->create();
        $roomType->amenityList()->attach($amenities->pluck('id')->all());

        $res = $this->withToken($this->editorToken())
            ->putJson("/api/cms/room-types/{$roomType->uuid}", ['amenities' => []])
            ->assertOk();

        $this->assertCount(0, $res->json('data.amenities'));
        $this->assertDatabaseCount('amenity_room_type', 0);
    }

    public function test_banner_is_the_first_image_by_sort_order(): void
    {
        Storage::fake('public');
        $roomType = RoomType::factory()->create();
        $token    = $this->editorToken();

        $this->withToken($token)->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
            'image' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertStatus(201);

        $res = $this->getJson("/api/public/room-types/{$roomType->uuid}")->assertOk();

        $this->assertNotEmpty($res->json('data.banner'));
        $this->assertSame($res->json('data.images.0.url'), $res->json('data.banner'));
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
