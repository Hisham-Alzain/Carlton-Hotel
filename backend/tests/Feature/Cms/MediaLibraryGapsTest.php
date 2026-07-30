<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Four gaps in the media library, each of which the library screen could feel
 * but not work around.
 *
 * - `attachExisting()` deduplicated against a snapshot taken *before* its loop,
 *   so one request naming two uuids that resolve to the same stored file put two
 *   copies of one photograph on the parent.
 * - `MediaResource` published no parent, while `GET /cms/media` filtered on one:
 *   a picker could narrow to "assets on room types" and not say which room type,
 *   and the filter demanded the FQCN nothing else in the API exposes.
 * - No usage count, and "how many placements share this file" is
 *   `count(*) where disk + path` — uncomputable from a page of results, so the
 *   delete button could not warn that a delete unlinks a file three records
 *   still render.
 * - A cleared `sort_order` was a 422 rather than "leave it as it is".
 */
class MediaLibraryGapsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, class-string> */
    private array $originalMorphMap = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        $this->originalMorphMap = Relation::morphMap();
    }

    protected function tearDown(): void
    {
        // `Relation::morphMap()` is process-wide.
        Relation::morphMap($this->originalMorphMap, false);
        parent::tearDown();
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');

        return $user->createToken('t')->plainTextToken;
    }

    /** @return array<string, mixed> */
    private function uploadToLibrary(string $token, array $extra = []): array
    {
        return $this->withToken($token)
            ->postJson('/api/cms/media', array_merge([
                'image' => UploadedFile::fake()->image('courtyard.jpg'),
            ], $extra))
            ->assertStatus(201)
            ->json('data');
    }

    /** @return array<string, array<string, mixed>> library items keyed by uuid */
    private function library(string $token, string $query = ''): array
    {
        $items = $this->withToken($token)
            ->getJson('/api/cms/media'.($query === '' ? '' : '?'.$query))
            ->assertOk()
            ->json('data.items');

        return collect($items)->keyBy('uuid')->all();
    }

    // ── (a) idempotency inside a single request ───────────────────────────

    /**
     * Two uuids, one file, one request — one placement.
     *
     * `distinct` on the request only rejects the *same* uuid twice. Attaching
     * copies rows, so a library entry and the copy of it already on a promotion
     * are two perfectly valid uuids that name one stored file, and both are legal
     * sources. Before the fix the second one was measured against a snapshot
     * taken before the loop, could not see the row the first one had just
     * written, and added a second copy of one photograph to the page.
     */
    public function test_two_uuids_naming_one_file_produce_a_single_placement(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();

        $asset = $this->uploadToLibrary($token, ['title' => 'Shared hero']);

        // The promotion's copy: a different uuid pointing at the same file.
        $copy = $this->withToken($token)
            ->postJson("/api/cms/promotions/{$promotion->uuid}/images/attach", ['media_uuids' => [$asset['uuid']]])
            ->assertStatus(201)
            ->json('data.0.uuid');

        $this->assertNotSame($asset['uuid'], $copy);

        $items = $this->withToken($token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", [
                'media_uuids' => [$asset['uuid'], $copy],
            ])
            ->assertStatus(201)
            // One row per requested uuid, in request order — the contract holds,
            // both entries are simply the same row.
            ->assertJsonCount(2, 'data')
            ->json('data');

        $this->assertSame(
            $items[0]['uuid'],
            $items[1]['uuid'],
            'two uuids for one file were answered with two different placements',
        );

        $this->assertCount(
            1,
            $roomType->refresh()->images,
            'the same photograph was placed on the room type twice',
        );

        // Library entry + promotion copy + one room-type copy.
        $this->assertSame(3, Media::count());
        $this->assertSame(1, Media::distinct()->count('path'));
    }

    /** Attaching one uuid twice over across several requests is unchanged. */
    public function test_repeating_the_request_still_adds_nothing(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $asset    = $this->uploadToLibrary($token);
        $url      = "/api/cms/room-types/{$roomType->uuid}/images/attach";

        $first  = $this->withToken($token)->postJson($url, ['media_uuids' => [$asset['uuid']]])->json('data.0.uuid');
        $second = $this->withToken($token)->postJson($url, ['media_uuids' => [$asset['uuid']]])->json('data.0.uuid');

        $this->assertSame($first, $second);
        $this->assertCount(1, $roomType->refresh()->images);
    }

    // ── (b) the parent a row sits on ──────────────────────────────────────

    public function test_the_library_says_which_record_each_asset_sits_on(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();

        $placed  = Media::factory()->attachedTo($roomType)->create();
        $library = Media::factory()->create();

        $items = $this->library($token);

        $this->assertArrayHasKey(
            'mediable_type',
            $items[$placed->uuid],
            'the library can be filtered by parent type but does not report one',
        );
        $this->assertArrayHasKey('mediable_uuid', $items[$placed->uuid]);

        $this->assertSame('room_type', $items[$placed->uuid]['mediable_type']);
        $this->assertSame($roomType->uuid, $items[$placed->uuid]['mediable_uuid']);

        // An asset nobody has placed says so, rather than omitting the keys.
        $this->assertNull($items[$library->uuid]['mediable_type']);
        $this->assertNull($items[$library->uuid]['mediable_uuid']);
    }

    /**
     * The token is the published spelling, and the FQCN the dashboard sends today
     * still resolves — the filter accepts both rather than swapping one for the
     * other.
     */
    public function test_the_type_filter_accepts_the_token_and_the_class_name(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();

        Media::factory()->count(2)->attachedTo($roomType)->create();
        Media::factory()->attachedTo($promotion)->create();
        Media::factory()->create();

        $this->assertCount(2, $this->library($token, 'mediable_type=room_type'));
        $this->assertCount(2, $this->library($token, http_build_query(['mediable_type' => RoomType::class])));
        $this->assertCount(2, $this->library($token, 'mediable_type=RoomType'));
        $this->assertCount(3, $this->library($token, 'mediable_type[in]=room_type,promotion'));

        // Unknown kinds of record match nothing, and say so with an empty list
        // rather than a 422 — a stale dashboard build must not break the screen.
        $this->assertCount(0, $this->library($token, 'mediable_type=not_a_model'));
    }

    /**
     * The reason the token is the published shape: the stored value changes the
     * day a media-bearing model joins `Relation::morphMap()`, and nothing outside
     * this resource should have to notice.
     */
    public function test_the_type_token_is_unchanged_when_the_model_joins_the_morph_map(): void
    {
        Relation::morphMap(['room_type' => RoomType::class]);

        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $media    = Media::factory()->attachedTo($roomType)->create();

        // The column now holds the alias, not the class name.
        $this->assertSame('room_type', $media->fresh()->mediable_type);

        $items = $this->library($token);
        $this->assertArrayHasKey('mediable_type', $items[$media->uuid]);
        $this->assertSame('room_type', $items[$media->uuid]['mediable_type']);

        $this->assertCount(1, $this->library($token, 'mediable_type=room_type'));
        $this->assertCount(1, $this->library($token, http_build_query(['mediable_type' => RoomType::class])));
    }

    // ── (c) how many placements share a file ──────────────────────────────

    public function test_the_library_counts_the_placements_that_share_a_file(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();

        $asset = $this->uploadToLibrary($token);
        $lone  = Media::factory()->create(['path' => 'cms/library/lone.jpg', 'file_name' => 'lone.jpg']);

        foreach ([
            "/api/cms/room-types/{$roomType->uuid}/images/attach",
            "/api/cms/promotions/{$promotion->uuid}/images/attach",
        ] as $url) {
            $this->withToken($token)->postJson($url, ['media_uuids' => [$asset['uuid']]])->assertStatus(201);
        }

        $items = $this->library($token);

        $this->assertArrayHasKey(
            'usage_count',
            $items[$asset['uuid']],
            'the library list does not say how many placements share a file, so the delete '
            .'button cannot warn that a delete unlinks one',
        );

        $this->assertSame(
            3,
            $items[$asset['uuid']]['usage_count'],
            'the library entry does not report the two other placements of its file',
        );
        $this->assertSame(1, $items[$lone->uuid]['usage_count']);

        // Deleting a placement drops the count, which is what tells the delete
        // button when it is about to take the file with it.
        $placement = Media::where('mediable_type', RoomType::class)->firstOrFail();
        $this->withToken($token)->deleteJson("/api/cms/media/{$placement->uuid}")->assertStatus(204);

        $this->assertSame(2, $this->library($token)[$asset['uuid']]['usage_count']);
    }

    /**
     * The count is a query-layer concern, so it is absent — not zero, and not one
     * query per row — wherever the query did not ask for it.
     */
    public function test_a_nested_image_list_neither_reports_nor_queries_the_count(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();
        Media::factory()->attachedTo($roomType)->create();

        $image = $this->withToken($token)
            ->getJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertOk()
            ->json('data.images.0');

        $this->assertArrayNotHasKey('usage_count', $image);
        $this->assertArrayNotHasKey('mediable_uuid', $image);

        // The parent is still named, because it is a column on the row.
        $this->assertArrayHasKey('mediable_type', $image);
        $this->assertSame('room_type', $image['mediable_type']);
    }

    // ── (d) an empty sort_order ───────────────────────────────────────────

    public function test_an_empty_sort_order_leaves_the_stored_order_alone(): void
    {
        $media = Media::factory()->create(['sort_order' => 3]);

        $this->withToken($this->editorToken())
            ->patchJson("/api/cms/media/{$media->uuid}", ['sort_order' => '', 'title' => 'Courtyard'])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.title', 'Courtyard');

        $this->assertSame(3, $media->refresh()->sort_order);
    }

    public function test_an_empty_sort_order_on_a_library_upload_falls_back_to_the_default(): void
    {
        $data = $this->uploadToLibrary($this->editorToken(), ['sort_order' => '']);

        $this->assertSame(0, $data['sort_order']);
    }

    /** An empty value means unset; a wrong one is still a client error. */
    public function test_a_bad_sort_order_is_still_rejected(): void
    {
        $media = Media::factory()->create(['sort_order' => 3]);
        $token = $this->editorToken();

        $this->withToken($token)
            ->patchJson("/api/cms/media/{$media->uuid}", ['sort_order' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_order');

        $this->withToken($token)
            ->patchJson("/api/cms/media/{$media->uuid}", ['sort_order' => 'later'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_order');

        $this->assertSame(3, $media->refresh()->sort_order);
    }
}
