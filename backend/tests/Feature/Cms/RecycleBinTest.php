<?php

namespace Tests\Feature\Cms;

use App\Models\DiningVenue;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The door on the recycle bin.
 *
 * `SoftDeleteTest` proved a CMS delete is recoverable *in the service layer*.
 * Nothing reached that layer: `route:list` had no restore, no force and no
 * trashed route, so an editor could not undo a delete through the API and no row
 * could ever leave the table. That second half is not cosmetic — `PurgesMedia`
 * fires on `forceDeleted`, so with no force route every deleted record's
 * photography stayed on disk for good.
 *
 * Three things have to hold, and each has a section below.
 *
 * 1. The routes exist, on every soft-deletable resource that has a delete, and
 *    the two that address a deleted record bind `->withTrashed()`. Without that
 *    flag implicit binding resolves through the model's soft-delete scope and
 *    `restore`/`force` 404 on the only kind of record they can ever be given —
 *    a recycle bin whose buttons cannot find anything in it.
 * 2. The round trip is genuine: gone from index and show, then back — with the
 *    children the cascade took and the media the delete deliberately kept.
 * 3. Emptying the bin is a different act from editing, and is gated as one.
 */
class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every CMS resource with a soft-deletable model and a `DELETE` route, as
     * `uri segment => binding parameter`.
     *
     * `SiteSetting` is the eighteenth soft-deletable model and is deliberately
     * absent: it has no per-row delete route at all (the settings form is one
     * bulk `PUT` through `UpsertSiteSettingsAction`), so nothing a client can do
     * puts a setting in the bin and the three verbs would address an empty set.
     *
     * @var array<string, string>
     */
    private const BIN_RESOURCES = [
        'room-types'         => 'roomType',
        'rooms'              => 'room',
        'facilities'         => 'facility',
        'dining-venues'      => 'diningVenue',
        'event-spaces'       => 'eventSpace',
        'amenities'          => 'amenity',
        'home-sliders'       => 'homeSlider',
        'pages'              => 'page',
        'promotions'         => 'promotion',
        'testimonials'       => 'testimonial',
        'faqs'               => 'faq',
        'experiences'        => 'experience',
        'gallery-categories' => 'galleryCategory',
        'gallery-items'      => 'galleryItem',
        'journal-posts'      => 'journalPost',
        'menu-categories'    => 'menuCategory',
        'menu-items'         => 'menuItem',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    // ── 1. The routes, and the binding flag they live or die by ───────────

    public function test_every_soft_deletable_cms_resource_has_all_three_bin_routes(): void
    {
        $missing = [];

        foreach (self::BIN_RESOURCES as $resource => $parameter) {
            foreach ([
                "GET api/cms/{$resource}/trashed",
                "POST api/cms/{$resource}/{{$parameter}}/restore",
                "DELETE api/cms/{$resource}/{{$parameter}}/force",
            ] as $expected) {
                [$verb, $uri] = explode(' ', $expected);

                if ($this->route($verb, $uri) === null) {
                    $missing[] = $expected;
                }
            }
        }

        $this->assertSame([], $missing, 'Unreachable recycle-bin verbs: '.implode(', ', $missing));
    }

    /**
     * The detail the whole feature turns on, pinned structurally as well as
     * behaviourally: implicit binding resolves a route parameter through the
     * model's `SoftDeletes` global scope unless the route says otherwise, so a
     * restore route without `->withTrashed()` cannot find a deleted record.
     */
    public function test_the_restore_and_force_routes_bind_with_trashed(): void
    {
        $unflagged = [];

        foreach (self::BIN_RESOURCES as $resource => $parameter) {
            foreach ([
                ['POST', "api/cms/{$resource}/{{$parameter}}/restore"],
                ['DELETE', "api/cms/{$resource}/{{$parameter}}/force"],
            ] as [$verb, $uri]) {
                $route = $this->route($verb, $uri);

                if ($route === null || ! $route->allowsTrashedBindings()) {
                    $unflagged[] = "{$verb} {$uri}";
                }
            }
        }

        $this->assertSame(
            [],
            $unflagged,
            'These bind through the soft-delete scope and will 404 on every record they target: '
            .implode(', ', $unflagged),
        );
    }

    /**
     * The behavioural half. This is the request an editor makes when they click
     * "undo", and it is the exact request that 404s if the route binding is left
     * at its default.
     */
    public function test_restore_resolves_a_record_that_delete_put_in_the_bin(): void
    {
        $editor = $this->editorToken();
        $page   = Page::factory()->create();

        $this->withToken($editor)->deleteJson("/api/cms/pages/{$page->uuid}")->assertStatus(204);

        $this->withToken($editor)
            ->postJson("/api/cms/pages/{$page->uuid}/restore")
            ->assertOk()
            ->assertJsonPath('data.uuid', $page->uuid);

        $this->assertNull($page->refresh()->deleted_at);
    }

    public function test_force_delete_resolves_a_record_that_delete_put_in_the_bin(): void
    {
        $page = Page::factory()->create();

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/pages/{$page->uuid}")
            ->assertStatus(204);

        $this->withToken($this->managerToken())
            ->deleteJson("/api/cms/pages/{$page->uuid}/force")
            ->assertStatus(204);

        $this->assertDatabaseCount('pages', 0);
    }

    /**
     * The guard on the flag: `withTrashed()` widens the binding to deleted rows,
     * it does not stop the binding failing. A uuid that names nothing must still
     * 404 rather than reach the service.
     */
    public function test_restore_still_404s_on_a_uuid_that_names_nothing(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/pages/'.\Illuminate\Support\Str::uuid().'/restore')
            ->assertNotFound();
    }

    /**
     * `/trashed` is a literal segment sitting where a uuid goes. Declared after
     * `/{page}` it would be swallowed by the show route and answered as a
     * missing record, so the bin listing has to come first in the file.
     */
    public function test_the_bin_route_is_not_swallowed_by_the_show_route(): void
    {
        Page::factory()->create();

        $this->withToken($this->editorToken())
            ->getJson('/api/cms/pages/trashed')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['items', 'meta']]);
    }

    // ── 2. The round trip ─────────────────────────────────────────────────

    public function test_a_deleted_record_leaves_every_read_route_and_comes_back(): void
    {
        $editor = $this->editorToken();
        $page   = Page::factory()->create(['slug' => 'about-us', 'is_active' => true]);
        Page::factory()->create(['slug' => 'contact', 'is_active' => true]);

        $this->withToken($editor)->deleteJson("/api/cms/pages/{$page->uuid}")->assertStatus(204);

        $this->withToken($editor)->getJson("/api/cms/pages/{$page->uuid}")->assertNotFound();
        $this->assertSame(
            ['contact'],
            $this->withToken($editor)->getJson('/api/cms/pages')->json('data.items.*.slug'),
        );

        $this->withToken($editor)->postJson("/api/cms/pages/{$page->uuid}/restore")->assertOk();

        $this->withToken($editor)->getJson("/api/cms/pages/{$page->uuid}")->assertOk();
        $this->assertEqualsCanonicalizing(
            ['about-us', 'contact'],
            $this->withToken($editor)->getJson('/api/cms/pages')->json('data.items.*.slug'),
        );
        $this->getJson('/api/public/pages/about-us')->assertOk();
    }

    /**
     * A restore that leaves the cascade children in the bin is not a restore —
     * the venue comes back with no menu. Driven over HTTP because the service
     * path was already covered; what was untested is that the route reaches it.
     */
    public function test_restore_brings_back_the_cascade_children_two_levels_down(): void
    {
        $editor   = $this->editorToken();
        $venue    = DiningVenue::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->forVenue($venue)->create(['is_active' => true]);
        MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id, 'is_active' => true]);

        $this->withToken($editor)->deleteJson("/api/cms/dining-venues/{$venue->uuid}")->assertStatus(204);
        $this->assertSame(0, MenuItem::count());

        $this->withToken($editor)->postJson("/api/cms/dining-venues/{$venue->uuid}/restore")->assertOk();

        $this->assertSame(1, MenuCategory::count());
        $this->assertSame(2, MenuItem::count());
        $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    /**
     * The reason `PurgesMedia` moved off `deleting`: a soft delete keeps the
     * media rows and the stored files, so the record comes back whole. If the
     * purge still ran on the recoverable delete this restore would hand back a
     * room type with no photography — data loss dressed as a safety feature.
     */
    public function test_restore_brings_back_the_records_media_and_the_file_survives_the_delete(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();

        $mediaUuid = $this->withToken($editor)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
                'image' => UploadedFile::fake()->image('suite.jpg'),
            ])
            ->assertStatus(201)
            ->json('data.uuid');

        $path = \App\Models\Media::where('uuid', $mediaUuid)->value('path');
        Storage::disk('public')->assertExists($path);

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);

        // Kept, not purged — this is what makes the restore below whole.
        $this->assertDatabaseHas('media', ['uuid' => $mediaUuid]);
        Storage::disk('public')->assertExists($path);

        $this->withToken($editor)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/restore")
            ->assertOk()
            ->assertJsonPath('data.images.0.uuid', $mediaUuid);

        Storage::disk('public')->assertExists($path);
    }

    public function test_the_bin_lists_only_deleted_rows_most_recently_deleted_first(): void
    {
        $editor = $this->editorToken();
        $first  = RoomType::factory()->create();
        $second = RoomType::factory()->create();
        $live   = RoomType::factory()->create();

        $this->travelTo(now()->subHour());
        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$first->uuid}")->assertStatus(204);
        $this->travelBack();
        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$second->uuid}")->assertStatus(204);

        $bin = $this->withToken($editor)->getJson('/api/cms/room-types/trashed')->assertOk();

        $this->assertSame([$second->uuid, $first->uuid], $bin->json('data.items.*.uuid'));
        $this->assertNotContains($live->uuid, $bin->json('data.items.*.uuid'));
        $this->assertSame(2, $bin->json('data.meta.total'));
    }

    // ── 3. Emptying the bin, and who may ──────────────────────────────────

    /**
     * The other half of why the force route had to exist: until it did, the
     * media rows and files of every deleted record accumulated with no verb able
     * to clear them.
     */
    public function test_force_delete_takes_the_children_the_media_rows_and_the_files(): void
    {
        $editor   = $this->editorToken();
        $category = GalleryCategory::factory()->create();
        $item     = GalleryItem::factory()->create(['gallery_category_id' => $category->id]);

        $mediaUuid = $this->withToken($editor)
            ->postJson("/api/cms/gallery-items/{$item->uuid}/images", [
                'image' => UploadedFile::fake()->image('lobby.jpg'),
            ])
            ->assertStatus(201)
            ->json('data.uuid');

        $path = \App\Models\Media::where('uuid', $mediaUuid)->value('path');

        $this->withToken($editor)->deleteJson("/api/cms/gallery-categories/{$category->uuid}")->assertStatus(204);

        $this->withToken($this->managerToken())
            ->deleteJson("/api/cms/gallery-categories/{$category->uuid}/force")
            ->assertStatus(204);

        $this->assertDatabaseCount('gallery_categories', 0);
        $this->assertDatabaseCount('gallery_items', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_bin_routes_reject_an_unauthenticated_caller(): void
    {
        $page = Page::factory()->create();

        $this->getJson('/api/cms/pages/trashed')->assertStatus(401);
        $this->postJson("/api/cms/pages/{$page->uuid}/restore")->assertStatus(401);
        $this->deleteJson("/api/cms/pages/{$page->uuid}/force")->assertStatus(401);
    }

    /**
     * The point of the split. `cms.edit` is the permission a junior content
     * editor holds; it buys writing and deleting, not undoing someone else's
     * delete and not emptying the bin.
     */
    public function test_cms_edit_alone_reaches_none_of_the_bin(): void
    {
        $page = Page::factory()->create();
        $page->delete();

        $token = $this->tokenFor(['cms.view', 'cms.edit']);

        $this->withToken($token)->getJson('/api/cms/pages/trashed')
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->withToken($token)->postJson("/api/cms/pages/{$page->uuid}/restore")
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->withToken($token)->deleteJson("/api/cms/pages/{$page->uuid}/force")
            ->assertStatus(403)->assertJsonPath('error_code', 'forbidden');

        $this->assertSame(1, Page::withTrashed()->count());
    }

    public function test_cms_restore_can_undo_a_delete_but_cannot_empty_the_bin(): void
    {
        $page = Page::factory()->create();
        $page->delete();

        $token = $this->tokenFor(['cms.view', 'cms.edit', 'cms.restore']);

        $this->withToken($token)->getJson('/api/cms/pages/trashed')->assertOk();
        $this->withToken($token)->deleteJson("/api/cms/pages/{$page->uuid}/force")->assertStatus(403);
        $this->withToken($token)->postJson("/api/cms/pages/{$page->uuid}/restore")->assertOk();

        $this->assertNull($page->refresh()->deleted_at);
    }

    public function test_cms_purge_can_empty_the_bin(): void
    {
        $page = Page::factory()->create();
        $page->delete();

        $token = $this->tokenFor(['cms.purge']);

        $this->withToken($token)->getJson('/api/cms/pages/trashed')->assertOk();
        $this->withToken($token)->deleteJson("/api/cms/pages/{$page->uuid}/force")->assertStatus(204);

        $this->assertDatabaseCount('pages', 0);
    }

    public function test_the_content_editor_preset_can_restore_but_not_purge(): void
    {
        $page = Page::factory()->create();
        $page->delete();

        $token = $this->roleToken('content_editor');

        $this->withToken($token)->deleteJson("/api/cms/pages/{$page->uuid}/force")->assertStatus(403);
        $this->withToken($token)->postJson("/api/cms/pages/{$page->uuid}/restore")->assertOk();
    }

    public function test_the_content_manager_preset_can_purge(): void
    {
        $page = Page::factory()->create();
        $page->delete();

        $this->withToken($this->roleToken('content_manager'))
            ->deleteJson("/api/cms/pages/{$page->uuid}/force")
            ->assertStatus(204);

        $this->assertDatabaseCount('pages', 0);
    }

    // ── 4. The media library stops advertising unreachable assets ─────────

    /**
     * `GET /cms/media` listed placements whose parent was in the bin: images
     * attached to records that appear in no index, no show route and no public
     * page, and whose `mediable_uuid` already came back null because the
     * `mediable` relation resolves through the parent's own soft-delete scope.
     */
    public function test_the_media_library_hides_assets_whose_parent_is_in_the_bin(): void
    {
        $editor = $this->editorToken();
        $doomed = RoomType::factory()->create();
        $kept   = RoomType::factory()->create();

        $goneUuid = $this->uploadTo($editor, "/api/cms/room-types/{$doomed->uuid}/images");
        $keptUuid = $this->uploadTo($editor, "/api/cms/room-types/{$kept->uuid}/images");

        $this->assertEqualsCanonicalizing(
            [$goneUuid, $keptUuid],
            $this->withToken($editor)->getJson('/api/cms/media')->json('data.items.*.uuid'),
        );

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$doomed->uuid}")->assertStatus(204);

        $library = $this->withToken($editor)->getJson('/api/cms/media')->assertOk();

        $this->assertSame([$keptUuid], $library->json('data.items.*.uuid'));
        $this->assertSame(1, $library->json('data.meta.total'));

        // Hidden, not destroyed — the row is what a restore brings back.
        $this->assertDatabaseHas('media', ['uuid' => $goneUuid]);
    }

    public function test_restoring_the_parent_returns_its_assets_to_the_library(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();

        $mediaUuid = $this->uploadTo($editor, "/api/cms/room-types/{$roomType->uuid}/images");

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);
        $this->assertSame([], $this->withToken($editor)->getJson('/api/cms/media')->json('data.items.*.uuid'));

        $this->withToken($editor)->postJson("/api/cms/room-types/{$roomType->uuid}/restore")->assertOk();

        $this->assertSame(
            [$mediaUuid],
            $this->withToken($editor)->getJson('/api/cms/media')->json('data.items.*.uuid'),
        );
    }

    /**
     * The guard on the scope. A parentless library upload is the whole point of
     * the library, and `whereHasMorph('*')` enumerates the morph types actually
     * present — with none present it matches nothing at all, so the unattached
     * rows have to be admitted alongside it rather than through it.
     */
    public function test_unattached_library_assets_survive_the_live_parent_scope(): void
    {
        $editor = $this->editorToken();

        $libraryUuid = $this->uploadTo($editor, '/api/cms/media');

        $this->assertSame(
            [$libraryUuid],
            $this->withToken($editor)->getJson('/api/cms/media')->json('data.items.*.uuid'),
        );

        // And still there once a placement exists and its parent is binned, so
        // the scope narrows to the attached half rather than to nothing.
        $roomType = RoomType::factory()->create();
        $this->uploadTo($editor, "/api/cms/room-types/{$roomType->uuid}/images");
        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);

        $this->assertSame(
            [$libraryUuid],
            $this->withToken($editor)->getJson('/api/cms/media')->json('data.items.*.uuid'),
        );
        $this->assertSame(
            [$libraryUuid],
            $this->withToken($editor)->getJson('/api/cms/media?unattached=true')->json('data.items.*.uuid'),
        );
    }

    public function test_force_deleting_the_parent_clears_its_library_rows_for_good(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();

        $mediaUuid = $this->uploadTo($editor, "/api/cms/room-types/{$roomType->uuid}/images");

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);
        $this->assertDatabaseHas('media', ['uuid' => $mediaUuid]);

        $this->withToken($this->managerToken())
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}/force")
            ->assertStatus(204);

        $this->assertDatabaseMissing('media', ['uuid' => $mediaUuid]);
        $this->assertSame([], $this->withToken($editor)->getJson('/api/cms/media')->json('data.items.*.uuid'));
    }

    // ── 5. A restore may not create the orphan the cascade prevents ───────

    /**
     * The delete cascade takes children down with the parent; nothing brought
     * them back up on their own. Restoring one directly answered 200 and
     * produced exactly what `CascadesSoftDeletes` exists to stop — a live,
     * bookable room under a room type that appears in no index, no show route
     * and no public page.
     */
    public function test_restoring_a_child_while_its_parent_is_still_binned_is_refused(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $room     = Room::factory()->create(['room_type_id' => $roomType->id]);

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);
        $this->assertNotNull($room->refresh()->deleted_at);

        $this->withToken($editor)
            ->postJson("/api/cms/rooms/{$room->uuid}/restore")
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'ancestor_trashed');

        $this->assertNotNull($room->refresh()->deleted_at);
    }

    /**
     * "Cannot restore", with nothing named, is a dead end an editor answers by
     * clicking again. The payload has to say which record to restore first and
     * carry enough for a dashboard to offer that as a button.
     */
    public function test_the_refusal_names_the_ancestor_that_must_be_restored_first(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $room     = Room::factory()->create(['room_type_id' => $roomType->id]);

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);

        $this->withToken($editor)
            ->postJson("/api/cms/rooms/{$room->uuid}/restore")
            ->assertStatus(409)
            ->assertJsonPath('context.ancestor.type', 'room_type')
            ->assertJsonPath('context.ancestor.uuid', $roomType->uuid)
            ->assertJsonPath('context.trashed_ancestors', [
                ['type' => 'room_type', 'uuid' => $roomType->uuid],
            ]);
    }

    /**
     * The check is to the root, not one level.
     *
     * A dish whose category is live but whose venue is binned is still an
     * orphan, and the live category is precisely what makes it look restorable.
     * The state is built the only way it occurs: the dish went to the bin on its
     * own an hour before the venue did, so the venue's cascade did not touch it
     * and restoring the category — which restores only what went down *with* it
     * — left it behind.
     */
    public function test_the_ancestor_check_walks_to_the_root_not_just_one_level(): void
    {
        $editor   = $this->editorToken();
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        $item     = MenuItem::factory()->create(['menu_category_id' => $category->id]);

        $this->travelTo(now()->subHour());
        $item->delete();
        $this->travelBack();

        $venue->delete();
        $category->refresh()->restore();

        // The shape under test: venue binned, category live, dish binned.
        $this->assertNotNull($venue->refresh()->deleted_at);
        $this->assertNull($category->refresh()->deleted_at);
        $this->assertNotNull($item->refresh()->deleted_at);

        $this->withToken($editor)
            ->postJson("/api/cms/menu-items/{$item->uuid}/restore")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ancestor_trashed')
            ->assertJsonPath('context.ancestor.type', 'dining_venue')
            ->assertJsonPath('context.ancestor.uuid', $venue->uuid)
            ->assertJsonPath('context.trashed_ancestors', [
                ['type' => 'dining_venue', 'uuid' => $venue->uuid],
            ]);

        $this->assertNotNull($item->refresh()->deleted_at);
    }

    /**
     * Refused, not blocked. Restoring the named ancestor clears the way, which
     * is what makes the error actionable rather than a wall.
     */
    public function test_restoring_the_named_ancestor_first_lets_the_child_come_back(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $room     = Room::factory()->create(['room_type_id' => $roomType->id]);

        // The room goes on its own first, so the type's cascade neither takes it
        // nor gives it back — otherwise restoring the type would resurrect it
        // and there would be nothing left to prove.
        $this->travelTo(now()->subHour());
        $room->delete();
        $this->travelBack();

        $this->withToken($editor)->deleteJson("/api/cms/room-types/{$roomType->uuid}")->assertStatus(204);

        $this->withToken($editor)->postJson("/api/cms/rooms/{$room->uuid}/restore")->assertStatus(409);

        $this->withToken($editor)->postJson("/api/cms/room-types/{$roomType->uuid}/restore")->assertOk();
        $this->assertNotNull($room->refresh()->deleted_at, 'the room was a separate decision and stays binned');

        $this->withToken($editor)
            ->postJson("/api/cms/rooms/{$room->uuid}/restore")
            ->assertOk()
            ->assertJsonPath('data.uuid', $room->uuid);

        $this->assertNull($room->refresh()->deleted_at);
    }

    /**
     * The guard narrows to trashed ancestors and nothing else: an ordinary
     * child restore under a live parent is untouched, as is a record with no
     * parent at all.
     */
    public function test_a_child_under_a_live_parent_still_restores(): void
    {
        $editor   = $this->editorToken();
        $roomType = RoomType::factory()->create();
        $room     = Room::factory()->create(['room_type_id' => $roomType->id]);

        $this->withToken($editor)->deleteJson("/api/cms/rooms/{$room->uuid}")->assertStatus(204);

        $this->withToken($editor)->postJson("/api/cms/rooms/{$room->uuid}/restore")->assertOk();

        $this->assertNull($room->refresh()->deleted_at);
        $this->assertNull($roomType->refresh()->deleted_at);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function route(string $verb, string $uri): ?\Illuminate\Routing\Route
    {
        foreach (app(Router::class)->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($verb, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    /** @param list<string> $permissions */
    private function tokenFor(array $permissions): string
    {
        $user = User::factory()->staff()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function roleToken(string $role): string
    {
        $user = User::factory()->staff()->create();
        $user->assignRole($role);

        return $user->createToken('t')->plainTextToken;
    }

    /** The everyday CMS actor: writes, and can take a delete back. */
    private function editorToken(): string
    {
        return $this->tokenFor(['cms.view', 'cms.edit', 'cms.restore']);
    }

    /** Senior editorial — the only actor that may empty the bin. */
    private function managerToken(): string
    {
        return $this->tokenFor(['cms.view', 'cms.edit', 'cms.restore', 'cms.purge']);
    }

    private function uploadTo(string $token, string $path): string
    {
        return $this->withToken($token)
            ->postJson($path, ['image' => UploadedFile::fake()->image('photo.jpg')])
            ->assertStatus(201)
            ->json('data.uuid');
    }
}
