<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `media` used to be reachable only as a side effect of
 * `POST /api/cms/{parent}/{uuid}/images`: an editor could not see what had been
 * uploaded, could not put one photograph on two pages, and could not write alt
 * text at all. These tests cover the library that fixes that — index, parentless
 * upload, metadata edit, delete, and attaching existing assets — plus the two
 * invariants the nullable morph must not break: library rows stay invisible to
 * every parent's `images` relation, and a shared file outlives any one row that
 * references it.
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function tokenWith(string ...$permissions): string
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user->createToken('t')->plainTextToken;
    }

    private function editorToken(): string
    {
        return $this->tokenWith('cms.edit');
    }

    /** Upload into the library and return the created row. */
    private function uploadToLibrary(string $token, array $extra = []): array
    {
        return $this->withToken($token)
            ->postJson('/api/cms/media', array_merge([
                'image' => UploadedFile::fake()->image('courtyard.jpg'),
            ], $extra))
            ->assertStatus(201)
            ->json('data');
    }

    // ── The nullable morph, which is what makes a library possible ─────────

    /**
     * The whole feature rests on `mediable_type`/`mediable_id` being nullable.
     * Without the migration this row cannot be written at all.
     */
    public function test_a_media_row_can_exist_with_no_parent(): void
    {
        $media = Media::factory()->create();

        $this->assertNull($media->mediable_type);
        $this->assertNull($media->mediable_id);
        $this->assertDatabaseHas('media', ['uuid' => $media->uuid, 'mediable_type' => null]);
    }

    /**
     * The safety claim behind relaxing NOT NULL: every `morphMany` compiles to
     * `where mediable_type = ?`, and SQL equality never matches NULL — so an
     * unattached row cannot surface on an entity that does not own it, in the
     * CMS or on the public site.
     */
    public function test_library_rows_never_leak_into_a_parents_images(): void
    {
        $roomType = RoomType::factory()->create();
        Media::factory()->count(3)->create();

        $this->assertCount(0, $roomType->images);
        $this->assertSame(3, Media::count());

        $this->withToken($this->editorToken())
            ->getJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertOk()
            ->assertJsonCount(0, 'data.images');

        $this->getJson('/api/public/room-types')
            ->assertOk()
            ->assertJsonCount(0, 'data.items.0.images');
    }

    // ── POST /api/cms/media ───────────────────────────────────────────────

    public function test_editor_can_upload_to_the_library_with_no_parent(): void
    {
        $data = $this->uploadToLibrary($this->editorToken(), [
            'alt_text'   => ['en' => 'The courtyard at dusk.', 'ar' => 'الساحة عند الغروب.'],
            'title'      => 'Courtyard at dusk',
            'sort_order' => 2,
        ]);

        $this->assertSame('Courtyard at dusk', $data['title']);
        $this->assertSame('The courtyard at dusk.', $data['alt_text']['en']);
        $this->assertSame('الساحة عند الغروب.', $data['alt_text']['ar']);
        $this->assertSame(2, $data['sort_order']);
        $this->assertSame('courtyard.jpg', $data['file_name']);
        $this->assertNotEmpty($data['url']);

        $media = Media::where('uuid', $data['uuid'])->firstOrFail();

        $this->assertNull($media->mediable_type);
        $this->assertStringStartsWith('cms/library/', $media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_library_upload_returns_the_standard_created_envelope(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/media', ['image' => UploadedFile::fake()->image('a.jpg')])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['uuid', 'url', 'file_name', 'alt_text', 'title', 'mime_type', 'size', 'sort_order'],
            ]);
    }

    public function test_library_upload_keeps_the_existing_file_contract(): void
    {
        $token = $this->editorToken();

        $this->withToken($token)
            ->postJson('/api/cms/media', ['image' => UploadedFile::fake()->create('handbook.pdf', 12, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('image');

        $this->withToken($token)
            ->postJson('/api/cms/media', ['image' => UploadedFile::fake()->image('huge.jpg')->size(5121)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->withToken($token)
            ->postJson('/api/cms/media', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_library_upload_rejects_over_long_metadata(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/media', [
                'image'    => UploadedFile::fake()->image('a.jpg'),
                'title'    => str_repeat('x', 256),
                'alt_text' => ['en' => str_repeat('y', 256)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'alt_text.en']);
    }

    // ── GET /api/cms/media ────────────────────────────────────────────────

    public function test_index_is_paginated_and_enveloped(): void
    {
        Media::factory()->count(3)->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/media')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['items', 'meta' => ['current_page', 'per_page', 'total', 'last_page']],
            ]);
    }

    public function test_index_lists_attached_and_unattached_assets_newest_first(): void
    {
        $roomType = RoomType::factory()->create();

        $old = Media::factory()->attachedTo($roomType)->create(['created_at' => now()->subDay()]);
        $new = Media::factory()->create();

        $items = $this->withToken($this->editorToken())
            ->getJson('/api/cms/media')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(2, $items);
        $this->assertSame($new->uuid, $items[0]['uuid'], 'the library must open on the newest asset');
        $this->assertSame($old->uuid, $items[1]['uuid']);
    }

    public function test_index_filters_by_the_unattached_flag(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();

        Media::factory()->count(2)->create();
        Media::factory()->attachedTo($roomType)->create();

        $unattached = $this->withToken($token)->getJson('/api/cms/media?unattached=true')->assertOk();
        $this->assertCount(2, $unattached->json('data.items'));

        $attached = $this->withToken($token)->getJson('/api/cms/media?unattached=false')->assertOk();
        $this->assertCount(1, $attached->json('data.items'));

        // Rule 2: a present-but-empty param means "no filter", not "false".
        $all = $this->withToken($token)->getJson('/api/cms/media?unattached=')->assertOk();
        $this->assertCount(3, $all->json('data.items'));
    }

    /**
     * Rule 3: a value the DSL cannot interpret is a client error. A typo that
     * silently returned the whole library is indistinguishable from a real answer.
     */
    public function test_an_unparseable_unattached_flag_is_rejected_rather_than_guessed(): void
    {
        Media::factory()->count(2)->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/media?unattached=trve')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('unattached');
    }

    public function test_index_filters_by_mime(): void
    {
        $token = $this->editorToken();

        Media::factory()->count(2)->mime('image/webp')->create();
        Media::factory()->mime('image/png')->create();

        $webp = $this->withToken($token)->getJson('/api/cms/media?' . http_build_query(['mime' => 'image/webp']))->assertOk();
        $this->assertCount(2, $webp->json('data.items'));

        // The column name still works, and an explicit `mime_type` wins over `mime`.
        $png = $this->withToken($token)->getJson('/api/cms/media?' . http_build_query(['mime_type' => 'image/png']))->assertOk();
        $this->assertCount(1, $png->json('data.items'));

        $both = $this->withToken($token)->getJson('/api/cms/media?' . http_build_query(['mime' => ['in' => 'image/webp,image/png']]))->assertOk();
        $this->assertCount(3, $both->json('data.items'));
    }

    public function test_index_filters_by_mediable_type(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();

        Media::factory()->count(2)->attachedTo($roomType)->create();
        Media::factory()->attachedTo($promotion)->create();
        Media::factory()->create();

        $res = $this->withToken($token)
            ->getJson('/api/cms/media?' . http_build_query(['mediable_type' => RoomType::class]))
            ->assertOk();

        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_index_search_scans_file_name_title_and_alt_text(): void
    {
        $token = $this->editorToken();

        Media::factory()->create(['file_name' => 'rooftop-pool.jpg', 'path' => 'cms/library/rooftop-pool.jpg']);
        Media::factory()->create(['title' => 'Rooftop at night']);
        Media::factory()->create(['alt_text' => ['en' => 'A rooftop terrace.']]);
        Media::factory()->create(['file_name' => 'lobby.jpg', 'path' => 'cms/library/lobby.jpg']);

        $res = $this->withToken($token)->getJson('/api/cms/media?search=rooftop')->assertOk();

        $this->assertCount(3, $res->json('data.items'));
    }

    public function test_index_honours_per_page(): void
    {
        Media::factory()->count(4)->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/media?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonCount(2, 'data.items');
    }

    // ── PATCH /api/cms/media/{media} ──────────────────────────────────────

    public function test_editor_can_set_alt_text_title_and_sort_order(): void
    {
        $media = Media::factory()->create();

        $this->withToken($this->editorToken())
            ->patchJson("/api/cms/media/{$media->uuid}", [
                'alt_text'   => ['en' => 'Marble lobby.', 'ar' => 'بهو من الرخام.'],
                'title'      => 'Lobby',
                'sort_order' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.alt_text.en', 'Marble lobby.')
            ->assertJsonPath('data.alt_text.ar', 'بهو من الرخام.')
            ->assertJsonPath('data.title', 'Lobby')
            ->assertJsonPath('data.sort_order', 5);

        $media->refresh();
        $this->assertSame('Marble lobby.', $media->getTranslation('alt_text', 'en'));
        $this->assertSame(5, $media->sort_order);
    }

    public function test_patch_leaves_untouched_fields_alone(): void
    {
        $media = Media::factory()->described()->create(['sort_order' => 3]);

        $this->withToken($this->editorToken())
            ->patchJson("/api/cms/media/{$media->uuid}", ['sort_order' => 7])
            ->assertOk()
            ->assertJsonPath('data.title', 'Courtyard at dusk')
            ->assertJsonPath('data.alt_text.en', 'A lit courtyard at dusk.')
            ->assertJsonPath('data.sort_order', 7);
    }

    public function test_patch_rejects_invalid_metadata(): void
    {
        $media = Media::factory()->create();
        $token = $this->editorToken();

        $this->withToken($token)
            ->patchJson("/api/cms/media/{$media->uuid}", ['sort_order' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_order');

        $this->withToken($token)
            ->patchJson("/api/cms/media/{$media->uuid}", ['title' => str_repeat('x', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->withToken($token)
            ->patchJson("/api/cms/media/{$media->uuid}", ['alt_text' => ['en' => ['nested']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alt_text.en');
    }

    public function test_patching_unknown_media_is_a_404(): void
    {
        $this->withToken($this->editorToken())
            ->patchJson('/api/cms/media/' . Str::uuid(), ['title' => 'x'])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    // ── DELETE /api/cms/media/{media} ─────────────────────────────────────

    public function test_editor_can_delete_a_library_asset_and_its_file(): void
    {
        $token = $this->editorToken();
        $data  = $this->uploadToLibrary($token);
        $path  = Media::where('uuid', $data['uuid'])->value('path');

        Storage::disk('public')->assertExists($path);

        $this->withToken($token)
            ->deleteJson("/api/cms/media/{$data['uuid']}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('media', ['uuid' => $data['uuid']]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_unknown_media_is_a_404(): void
    {
        $this->withToken($this->editorToken())
            ->deleteJson('/api/cms/media/' . Str::uuid())
            ->assertStatus(404);
    }

    // ── POST /api/cms/{parent}/{uuid}/images/attach ────────────────────────

    public function test_editor_can_attach_a_library_asset_to_a_parent(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $asset    = $this->uploadToLibrary($token, ['title' => 'Shared hero']);

        $items = $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", [
                'media_uuids' => [$asset['uuid']],
            ])
            ->assertStatus(201)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Shared hero')
            ->json('data');

        // A copy, not a move: the library entry survives and the parent gets its
        // own row pointing at the same stored file.
        $this->assertNotSame($asset['uuid'], $items[0]['uuid']);
        $this->assertDatabaseHas('media', ['uuid' => $asset['uuid'], 'mediable_type' => null]);
        $this->assertDatabaseHas('media', [
            'uuid'          => $items[0]['uuid'],
            'mediable_type' => RoomType::class,
            'mediable_id'   => $roomType->id,
        ]);

        $source   = Media::where('uuid', $asset['uuid'])->firstOrFail();
        $attached = Media::where('uuid', $items[0]['uuid'])->firstOrFail();
        $this->assertSame($source->path, $attached->path);
        $this->assertSame($source->disk, $attached->disk);

        $this->assertCount(1, $roomType->refresh()->images);
    }

    /** The point of the feature: one upload, several entities. */
    public function test_one_asset_can_serve_two_entities(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();
        $asset     = $this->uploadToLibrary($token);

        foreach ([
            "/api/cms/room-types/{$roomType->uuid}/images/attach",
            "/api/cms/promotions/{$promotion->uuid}/images/attach",
        ] as $url) {
            $this->withToken($token)
                ->postJson($url, ['media_uuids' => [$asset['uuid']]])
                ->assertStatus(201);
        }

        $this->assertCount(1, $roomType->refresh()->images);
        $this->assertCount(1, $promotion->refresh()->images);

        // Three rows, one file.
        $this->assertSame(3, Media::count());
        $this->assertSame(1, Media::distinct()->count('path'));
        Storage::disk('public')->assertExists(Media::value('path'));
    }

    /**
     * Deleting one placement of a shared asset must not unlink the file the other
     * placements still render. Without the shared-path check in
     * `MediaService::deleteRow()` the promotion's image 404s from the CDN while
     * its row still says it exists.
     */
    public function test_deleting_one_placement_leaves_the_shared_file_intact(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();
        $asset     = $this->uploadToLibrary($token);
        $path      = Media::where('uuid', $asset['uuid'])->value('path');

        $roomTypeMedia = $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", ['media_uuids' => [$asset['uuid']]])
            ->assertStatus(201)
            ->json('data.0.uuid');

        $this->withToken($token)
            ->postJson("/api/cms/promotions/{$promotion->uuid}/images/attach", ['media_uuids' => [$asset['uuid']]])
            ->assertStatus(201);

        // Through the nested route, which still enforces its own parent scoping.
        $this->withToken($token)
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}/images/{$roomTypeMedia}")
            ->assertStatus(204);

        Storage::disk('public')->assertExists($path);
        $this->assertCount(1, $promotion->refresh()->images);

        // And the library entry too — still two referents, still no unlink.
        $this->withToken($token)->deleteJson("/api/cms/media/{$asset['uuid']}")->assertStatus(204);
        Storage::disk('public')->assertExists($path);

        // Last referent gone → the file goes with it.
        $last = Media::firstOrFail();
        $this->withToken($token)->deleteJson("/api/cms/media/{$last->uuid}")->assertStatus(204);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_attaching_several_assets_appends_them_in_order(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();

        // An image already uploaded straight onto the parent occupies sort_order 0.
        $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", ['image' => UploadedFile::fake()->image('first.jpg')])
            ->assertStatus(201);

        $first  = $this->uploadToLibrary($token, ['title' => 'second']);
        $second = $this->uploadToLibrary($token, ['title' => 'third']);

        $items = $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", [
                'media_uuids' => [$first['uuid'], $second['uuid']],
            ])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertSame(['second', 'third'], array_column($items, 'title'));
        $this->assertSame([1, 2], array_column($items, 'sort_order'));
        $this->assertSame([0, 1, 2], $roomType->refresh()->images->pluck('sort_order')->all());
    }

    /**
     * A double-submitted form must not put the same photograph on a page twice.
     */
    public function test_attaching_the_same_asset_twice_is_idempotent(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $asset    = $this->uploadToLibrary($token);
        $url      = "/api/cms/room-types/{$roomType->uuid}/images/attach";

        $first = $this->withToken($token)->postJson($url, ['media_uuids' => [$asset['uuid']]])
            ->assertStatus(201)->json('data.0.uuid');

        $second = $this->withToken($token)->postJson($url, ['media_uuids' => [$asset['uuid']]])
            ->assertStatus(201)->json('data.0.uuid');

        $this->assertSame($first, $second);
        $this->assertCount(1, $roomType->refresh()->images);
        $this->assertSame(2, Media::count());
    }

    /** An already-placed asset is a legal source — that is how reuse works. */
    public function test_an_attached_asset_can_be_attached_elsewhere(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();

        $hero = $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", ['image' => UploadedFile::fake()->image('hero.jpg')])
            ->assertStatus(201)
            ->json('data.uuid');

        $this->withToken($token)
            ->postJson("/api/cms/promotions/{$promotion->uuid}/images/attach", ['media_uuids' => [$hero]])
            ->assertStatus(201);

        $this->assertCount(1, $promotion->refresh()->images);
        $this->assertCount(1, $roomType->refresh()->images);
    }

    public function test_attach_rejects_an_unknown_or_malformed_payload(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $url      = "/api/cms/room-types/{$roomType->uuid}/images/attach";
        $asset    = Media::factory()->create();

        $this->withToken($token)->postJson($url, [])
            ->assertStatus(422)->assertJsonValidationErrors('media_uuids');

        $this->withToken($token)->postJson($url, ['media_uuids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('media_uuids');

        $this->withToken($token)->postJson($url, ['media_uuids' => [(string) Str::uuid()]])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('media_uuids.0');

        // `distinct`: a repeated uuid would collapse silently, so say so instead.
        $this->withToken($token)->postJson($url, ['media_uuids' => [$asset->uuid, $asset->uuid]])
            ->assertStatus(422)->assertJsonValidationErrors('media_uuids.0');

        $this->assertCount(0, $roomType->refresh()->images);
    }

    public function test_attaching_to_an_unknown_parent_is_a_404(): void
    {
        $asset = Media::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/room-types/' . Str::uuid() . '/images/attach', ['media_uuids' => [$asset->uuid]])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');
    }

    // ── Alt text reaches the nested resources too ─────────────────────────

    public function test_alt_text_and_title_reach_a_parents_image_list(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $asset    = $this->uploadToLibrary($token, [
            'alt_text' => ['en' => 'Sunlit suite.', 'ar' => 'جناح مشمس.'],
            'title'    => 'Suite',
        ]);

        $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", ['media_uuids' => [$asset['uuid']]])
            ->assertStatus(201);

        $this->withToken($token)
            ->getJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertOk()
            ->assertJsonPath('data.images.0.alt_text.en', 'Sunlit suite.')
            ->assertJsonPath('data.images.0.alt_text.ar', 'جناح مشمس.')
            ->assertJsonPath('data.images.0.title', 'Suite');

        // And on the public site, where the alt attribute actually matters.
        $this->getJson('/api/public/room-types')
            ->assertOk()
            ->assertJsonPath('data.items.0.images.0.alt_text.en', 'Sunlit suite.');
    }

    public function test_alt_text_is_an_empty_map_when_no_editor_has_written_one(): void
    {
        Media::factory()->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/media')
            ->assertOk()
            ->assertJsonPath('data.items.0.alt_text', [])
            ->assertJsonPath('data.items.0.title', null);
    }

    // ── Auth (401) ────────────────────────────────────────────────────────

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $roomType = RoomType::factory()->create();
        $media    = Media::factory()->create();

        $this->getJson('/api/cms/media')->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->postJson('/api/cms/media', ['image' => UploadedFile::fake()->image('a.jpg')])->assertStatus(401);
        $this->patchJson("/api/cms/media/{$media->uuid}", ['title' => 'x'])->assertStatus(401);
        $this->deleteJson("/api/cms/media/{$media->uuid}")->assertStatus(401);
        $this->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", ['media_uuids' => [$media->uuid]])
            ->assertStatus(401);

        $this->assertDatabaseHas('media', ['uuid' => $media->uuid]);
    }

    // ── Permissions (403) ─────────────────────────────────────────────────

    public function test_staff_without_a_cms_permission_is_forbidden(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/cms/media')->assertStatus(403);
    }

    public function test_cms_view_alone_reads_the_library_but_cannot_change_it(): void
    {
        $roomType = RoomType::factory()->create();
        $media    = Media::factory()->create();

        $viewer = $this->tokenWith('cms.view');

        $this->withToken($viewer)->getJson('/api/cms/media')->assertOk();

        $this->withToken($viewer)
            ->postJson('/api/cms/media', ['image' => UploadedFile::fake()->image('a.jpg')])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->withToken($viewer)->patchJson("/api/cms/media/{$media->uuid}", ['title' => 'x'])->assertStatus(403);
        $this->withToken($viewer)->deleteJson("/api/cms/media/{$media->uuid}")->assertStatus(403);
        $this->withToken($viewer)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", ['media_uuids' => [$media->uuid]])
            ->assertStatus(403);

        $this->assertDatabaseHas('media', ['uuid' => $media->uuid, 'title' => null]);
        $this->assertDatabaseCount('media', 1);
    }
}
