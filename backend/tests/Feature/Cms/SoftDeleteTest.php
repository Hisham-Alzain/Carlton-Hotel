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
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Cms\DiningVenueService;
use App\Services\Cms\GalleryCategoryService;
use App\Services\Cms\RoomTypeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An editorial delete is now recoverable — and must be indistinguishable from the
 * old permanent one to every client.
 *
 * The external contract is fixed: `DELETE` answers 204 and the record is gone
 * from every index and every show route, public and CMS. A dashboard team is
 * already building against it. Everything soft deletes add lives behind that
 * line; nothing about it may move.
 *
 * Three things had to be got right for that to hold, and each has a section
 * below:
 *
 * 1. Invisibility — the `SoftDeletes` global scope, plus route binding refusing
 *    to resolve a trashed key.
 * 2. The cascade. `ON DELETE CASCADE` never fires on a soft delete, so a marked
 *    parent used to leave its children live and publishable.
 *    `CascadesSoftDeletes` carries the mark down, and brings it back up on
 *    restore.
 * 3. Natural keys. `unique` indexes and `unique:`/`exists:` rules both counted
 *    trashed rows, so a deleted page kept its slug reserved forever.
 */
class SoftDeleteTest extends TestCase
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

    // ── 1. The wire contract has not moved ────────────────────────────────

    public function test_delete_still_answers_204_and_the_record_404s_on_the_cms_show_route(): void
    {
        $token = $this->editorToken();
        $page  = Page::factory()->create();

        $this->withToken($token)->getJson("/api/cms/pages/{$page->uuid}")->assertOk();

        $this->withToken($token)->deleteJson("/api/cms/pages/{$page->uuid}")->assertStatus(204);

        // Route binding resolves through the model's global scope, so the uuid no
        // longer names anything — the CMS cannot read back what it deleted.
        $this->withToken($token)->getJson("/api/cms/pages/{$page->uuid}")->assertNotFound();
        $this->assertSoftDeleted($page);
    }

    public function test_a_deleted_record_is_absent_from_the_cms_index(): void
    {
        $token = $this->editorToken();
        $gone  = Page::factory()->create(['slug' => 'gone']);
        Page::factory()->create(['slug' => 'kept']);

        $this->withToken($token)->deleteJson("/api/cms/pages/{$gone->uuid}")->assertStatus(204);

        $slugs = $this->withToken($token)->getJson('/api/cms/pages')
            ->assertOk()
            ->json('data.items.*.slug');

        $this->assertSame(['kept'], $slugs);
    }

    public function test_a_deleted_record_is_absent_from_the_public_index_and_show(): void
    {
        $roomType = RoomType::factory()->create(['is_active' => true]);
        RoomType::factory()->create(['is_active' => true]);

        $this->getJson('/api/public/room-types')->assertOk()->assertJsonCount(2, 'data.items');

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(204);

        $this->getJson('/api/public/room-types')->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson("/api/public/room-types/{$roomType->uuid}")->assertNotFound();
    }

    // ── 2. The cascade the database can no longer perform ─────────────────

    /**
     * `rooms.room_type_id` is `ON DELETE CASCADE`. Without the application-level
     * cascade the rooms stay live and `/api/public/rooms` keeps listing them under
     * a room type that no longer exists as far as anything else is concerned.
     */
    public function test_deleting_a_room_type_hides_its_rooms_from_the_public_site(): void
    {
        $roomType = RoomType::factory()->create();
        $other    = RoomType::factory()->create();

        Room::factory()->count(2)->create(['room_type_id' => $roomType->id, 'is_active' => true]);
        Room::factory()->create(['room_type_id' => $other->id, 'is_active' => true]);

        $this->getJson('/api/public/rooms')->assertOk()->assertJsonCount(3, 'data.items');

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(204);

        // The sibling type's room is untouched — a cascade, not a purge.
        $this->getJson('/api/public/rooms')->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame(2, Room::onlyTrashed()->count());
    }

    /**
     * The two-level path, and the worst of the set: the public menu is read
     * through `MenuItemService::menuForVenue()`, which JOINS `menu_categories`
     * rather than asking the venue for them. Scoping the venue's own reads would
     * not have touched this query at all.
     */
    public function test_deleting_a_dining_venue_hides_its_menu_categories_and_dishes(): void
    {
        $venue    = DiningVenue::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->forVenue($venue)->create(['is_active' => true]);
        MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id, 'is_active' => true]);

        $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/dining-venues/{$venue->uuid}")
            ->assertStatus(204);

        // The venue itself is unreachable, so the nested route 404s on binding.
        $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")->assertNotFound();

        // And the rows underneath really did go down, two levels deep — otherwise
        // the same dishes would surface the moment anything else queried them.
        $this->assertSame(0, MenuCategory::count());
        $this->assertSame(0, MenuItem::count());
        $this->assertSame(1, MenuCategory::onlyTrashed()->count());
        $this->assertSame(2, MenuItem::onlyTrashed()->count());
    }

    public function test_deleting_a_venue_leaves_another_venues_menu_alone(): void
    {
        $venue = DiningVenue::factory()->create();
        $keep  = DiningVenue::factory()->create(['is_active' => true]);

        $doomed   = MenuCategory::factory()->forVenue($venue)->create();
        $survivor = MenuCategory::factory()->forVenue($keep)->create(['is_active' => true]);

        MenuItem::factory()->create(['menu_category_id' => $doomed->id]);
        MenuItem::factory()->create(['menu_category_id' => $survivor->id, 'is_active' => true]);

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/dining-venues/{$venue->uuid}")
            ->assertStatus(204);

        $this->getJson("/api/public/dining-venues/{$keep->uuid}/menu")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    /**
     * `GalleryItemService::indexPublic()` joins `gallery_categories` for its
     * chip-first ordering — the same blind spot as the menu.
     */
    public function test_deleting_a_gallery_category_hides_its_photographs_from_the_public_gallery(): void
    {
        $category = GalleryCategory::factory()->create(['is_active' => true]);
        $keep     = GalleryCategory::factory()->create(['is_active' => true]);

        GalleryItem::factory()->count(2)->create(['gallery_category_id' => $category->id, 'is_active' => true]);
        GalleryItem::factory()->create(['gallery_category_id' => $keep->id, 'is_active' => true]);

        $this->getJson('/api/public/gallery')->assertOk()->assertJsonCount(3, 'data.items');

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/gallery-categories/{$category->uuid}")
            ->assertStatus(204);

        $this->getJson('/api/public/gallery')->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/public/gallery-categories')->assertOk()->assertJsonCount(1, 'data.items');
    }

    /**
     * A category deleted on its own takes its dishes too — the cascade is not
     * special-cased to the venue path.
     */
    public function test_deleting_a_menu_category_hides_its_dishes(): void
    {
        $venue    = DiningVenue::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->forVenue($venue)->create(['is_active' => true]);
        $dish     = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_active' => true]);

        $category->delete();

        $this->assertSoftDeleted($dish);
        $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    // ── Restore: back up the same edges ───────────────────────────────────

    public function test_restoring_a_venue_brings_back_the_menu_the_cascade_took(): void
    {
        $venue    = DiningVenue::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->forVenue($venue)->create(['is_active' => true]);
        $dishes   = MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id, 'is_active' => true]);

        $service = app(DiningVenueService::class);
        $service->destroy($venue);

        $this->assertSame(0, MenuItem::count());

        $service->restore($venue);

        $this->assertNull($venue->refresh()->deleted_at);
        $this->assertSame(1, MenuCategory::count());
        $this->assertSame(2, MenuItem::count());

        $this->getJson("/api/public/dining-venues/{$venue->uuid}/menu")
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        foreach ($dishes as $dish) {
            $this->assertNull($dish->refresh()->deleted_at);
        }
    }

    /**
     * A dish the editor retired last week was a separate decision. Restoring the
     * venue must not publish it again — which is why the cascade restore matches
     * on `deleted_at >= the parent's` rather than restoring every trashed child.
     */
    public function test_restoring_does_not_resurrect_a_child_deleted_before_the_parent(): void
    {
        $venue    = DiningVenue::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->forVenue($venue)->create(['is_active' => true]);
        $kept     = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_active' => true]);
        $retired  = MenuItem::factory()->create(['menu_category_id' => $category->id, 'is_active' => true]);

        $this->travelTo(now()->subDay());
        $retired->delete();
        $this->travelBack();

        $service = app(DiningVenueService::class);
        $service->destroy($venue);
        $service->restore($venue);

        $this->assertNull($kept->refresh()->deleted_at);
        $this->assertNotNull($retired->fresh()?->deleted_at ?? MenuItem::withTrashed()->find($retired->id)->deleted_at);
        $this->assertSame(1, MenuItem::count());
    }

    // ── Emptying the bin ─────────────────────────────────────────────────

    public function test_force_delete_removes_the_row_and_its_cascade_descendants(): void
    {
        $category = GalleryCategory::factory()->create();
        GalleryItem::factory()->count(2)->create(['gallery_category_id' => $category->id]);

        $service = app(GalleryCategoryService::class);
        $service->destroy($category);
        $service->forceDestroy($category);

        $this->assertDatabaseCount('gallery_categories', 0);
        $this->assertDatabaseCount('gallery_items', 0);
    }

    public function test_the_bin_lists_only_deleted_rows_newest_first(): void
    {
        $first  = RoomType::factory()->create();
        $second = RoomType::factory()->create();
        RoomType::factory()->create();

        $service = app(RoomTypeService::class);

        $this->travelTo(now()->subHour());
        $service->destroy($first);
        $this->travelBack();
        $service->destroy($second);

        $bin = $service->trashed()['data'];

        $this->assertSame(2, $bin->total());
        $this->assertSame([$second->id, $first->id], $bin->pluck('id')->all());
    }

    // ── 3. Natural keys stop being reserved by rows nobody can see ────────

    /**
     * The end-to-end version of the constraint. Both halves have to be right: the
     * `unique:pages,slug` rule (via `LiveRowPresenceVerifier`) and the database's
     * own unique index (via the live-only partial index). Fixing one alone turns
     * a 422 into a 500.
     */
    public function test_a_slug_freed_by_a_delete_can_be_used_again(): void
    {
        $token = $this->editorToken();
        $page  = Page::factory()->create(['slug' => 'about-us']);

        $this->withToken($token)->deleteJson("/api/cms/pages/{$page->uuid}")->assertStatus(204);

        $this->withToken($token)->postJson('/api/cms/pages', [
            'slug'      => 'about-us',
            'title'     => ['en' => 'About Us', 'ar' => 'من نحن'],
            'content'   => ['en' => 'We are a hotel.', 'ar' => 'نحن فندق.'],
            'is_active' => true,
        ])->assertStatus(201)->assertJsonPath('data.slug', 'about-us');

        $this->getJson('/api/public/pages/about-us')->assertOk();
    }

    /**
     * The guard on the fix: scoping the index to live rows must not have made it
     * useless. Two *live* pages still cannot share a slug — at the rule and at
     * the index.
     */
    public function test_two_live_rows_still_cannot_share_a_slug(): void
    {
        Page::factory()->create(['slug' => 'about-us']);

        $this->withToken($this->editorToken())->postJson('/api/cms/pages', [
            'slug'      => 'about-us',
            'title'     => ['en' => 'About Us', 'ar' => 'من نحن'],
            'content'   => ['en' => 'x', 'ar' => 'x'],
            'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['slug']);

        $this->assertTrue($this->uniqueIndexRejectsDuplicate());
    }

    /**
     * `rooms.number` is `varchar(10)` and unique, so the "mangle the key on
     * delete" trick was never available here — room 101 has to be reusable after
     * 101 is retired.
     */
    public function test_a_room_number_freed_by_a_delete_can_be_used_again(): void
    {
        $type = RoomType::factory()->create();
        $room = Room::factory()->create(['room_type_id' => $type->id, 'number' => '101']);

        $room->delete();

        $replacement = Room::factory()->create(['room_type_id' => $type->id, 'number' => '101']);

        $this->assertNotSame($room->id, $replacement->id);
        $this->assertSame(1, Room::where('number', '101')->count());
    }

    /**
     * `site_settings` is upserted by `(group, key)` through Eloquent's
     * `updateOrCreate`, which honours the soft-delete scope: a trashed row is one
     * the upsert can neither find nor insert past a plain composite unique. That
     * is a 500 on a settings save, so the composite is scoped to live rows too.
     */
    public function test_a_deleted_setting_does_not_block_the_group_key_upsert(): void
    {
        $setting = SiteSetting::factory()->create(['group' => 'contact', 'key' => 'phone']);
        $setting->delete();

        $fresh = SiteSetting::updateOrCreate(
            ['group' => 'contact', 'key' => 'phone'],
            ['value' => '+963 11 000 0000', 'type' => $setting->type, 'is_active' => true],
        );

        $this->assertNotSame($setting->id, $fresh->id);
        $this->assertSame(1, SiteSetting::where('group', 'contact')->count());
    }

    /**
     * The other half of the presence verifier: `exists:` must refuse a trashed
     * parent, or the CMS would happily file a new photograph under a chip the
     * site will never render.
     */
    public function test_a_trashed_parent_is_rejected_by_the_exists_rule(): void
    {
        $category = GalleryCategory::factory()->create();
        $category->delete();

        $this->withToken($this->editorToken())
            ->postJson('/api/cms/gallery-items', [
                'gallery_category_uuid' => $category->uuid,
                'caption'               => ['en' => 'Orphan', 'ar' => 'يتيم'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gallery_category_uuid']);
    }

    /**
     * Insert a duplicate live slug straight past the validator, to prove the
     * database — not just the rule — is still enforcing uniqueness.
     */
    private function uniqueIndexRejectsDuplicate(): bool
    {
        try {
            Page::factory()->create(['slug' => 'about-us']);
        } catch (\Illuminate\Database\QueryException) {
            return true;
        }

        return false;
    }
}
