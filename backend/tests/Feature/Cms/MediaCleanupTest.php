<?php

namespace Tests\Feature\Cms;

use App\Jobs\PurgeMediaFile;
use App\Models\DiningVenue;
use App\Models\Experience;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Cms\DiningVenueService;
use App\Services\Cms\ExperienceService;
use App\Services\Cms\GalleryCategoryService;
use App\Services\Cms\MediaService;
use App\Services\Cms\PromotionService;
use App\Services\Cms\RoomTypeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Permanently destroying a content record must take its media rows — and the
 * files behind them — with it. A *recoverable* delete must not.
 *
 * Before `PurgesMedia`, every CMS delete left both behind forever: the rows
 * pointed at a parent that no longer existed and nothing ever unlinked the
 * files. The cascade paths were the worst of it, because a single room type or
 * dining venue takes a whole subtree of media with it and none of those child
 * deletes pass through a service at all.
 *
 * ## Why these tests changed with soft deletes
 *
 * They used to drive the purge through `DELETE /api/cms/...` and assert the media
 * was gone. That endpoint is now a *recoverable* delete: the row is marked, and
 * the whole point of `PurgesMedia` moving from `deleting` to `forceDeleted` is
 * that the photography survives so a restore comes back whole. Asserting the old
 * behaviour would be asserting the data loss.
 *
 * So the intent of every case is preserved and its trigger moved: the HTTP delete
 * now proves media *survives* (`test_a_recoverable_delete_keeps_its_media...`),
 * and the purge cases drive `forceDestroy()` — the service verb an eventual
 * "empty the bin" endpoint will call. The child-row counts moved with them: after
 * a soft delete the cascade children are still *rows*, marked rather than gone,
 * so those assertions became `assertSoftDeleted`/`onlyTrashed` where the point
 * was "the cascade really fired".
 */
class MediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function editorToken(): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cms.edit');

        return $user->createToken('t')->plainTextToken;
    }

    /**
     * A real upload placed on `$parent` through the same service the media
     * controllers call, so the file genuinely exists on the faked disk.
     */
    private function attach(Model $parent, string $name = 'photo.jpg'): Media
    {
        return app(MediaService::class)->attach($parent, UploadedFile::fake()->image($name))['data'];
    }

    /**
     * The recycle-bin guarantee. An editor's delete is recoverable, so it must
     * leave the images alone — a restore that hands back a room type with no
     * photography is data loss dressed as a safety feature.
     */
    public function test_a_recoverable_delete_keeps_its_media_rows_and_files(): void
    {
        $experience = Experience::factory()->create();
        $media      = $this->attach($experience);

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/experiences/{$experience->uuid}")
            ->assertStatus(204);

        $this->assertSoftDeleted($experience);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    /**
     * The cascade children keep their media too, so restoring the venue restores
     * a menu with its dish photography intact.
     */
    public function test_a_recoverable_delete_keeps_the_media_of_its_cascade_children(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        $dish     = MenuItem::factory()->create(['menu_category_id' => $category->id]);

        $dishMedia = $this->attach($dish, 'dish.jpg');

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/dining-venues/{$venue->uuid}")
            ->assertStatus(204);

        $this->assertSoftDeleted($dish);
        $this->assertDatabaseHas('media', ['id' => $dishMedia->id]);
        Storage::disk('public')->assertExists($dishMedia->path);
    }

    public function test_permanently_deleting_content_purges_its_media_rows_and_files(): void
    {
        $experience = Experience::factory()->create();
        $path       = $this->attach($experience)->path;

        Storage::disk('public')->assertExists($path);

        app(ExperienceService::class)->forceDestroy($experience);

        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * `rooms.room_type_id` is `ON DELETE CASCADE`, so on a hard delete the rooms
     * would vanish without Eloquent firing a single event for them and
     * `RoomService::destroy()` is never called. `CascadesSoftDeletes` takes them
     * through Eloquent first, which is the only way their photography is ever
     * reachable — which is why the cleanup cannot live in a service.
     */
    public function test_permanently_deleting_a_room_type_purges_the_images_of_the_rooms_it_cascades_to(): void
    {
        $roomType = RoomType::factory()->create();
        $rooms    = Room::factory()->count(2)->create(['room_type_id' => $roomType->id]);

        $typePath  = $this->attach($roomType, 'suite.jpg')->path;
        $roomPaths = $rooms->map(fn (Room $room): string => $this->attach($room, 'room.jpg')->path);

        $this->assertSame(3, Media::count());

        app(RoomTypeService::class)->forceDestroy($roomType);

        // The rooms really did go — otherwise this test would pass for the wrong
        // reason, with the media purged by some path that never saw them.
        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('media', 0);

        Storage::disk('public')->assertMissing($typePath);
        foreach ($roomPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * Two levels of cascade: venue → menu categories → dishes. The categories
     * carry no media themselves, so they exist in the walk purely to reach the
     * dish photography below them.
     */
    public function test_permanently_deleting_a_dining_venue_purges_its_menu_categories_and_dish_images(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        $dishes   = MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id]);

        $venuePath = $this->attach($venue, 'venue.jpg')->path;
        $dishPaths = $dishes->map(fn (MenuItem $dish): string => $this->attach($dish, 'dish.jpg')->path);

        $this->assertSame(3, Media::count());

        app(DiningVenueService::class)->forceDestroy($venue);

        $this->assertDatabaseCount('menu_categories', 0);
        $this->assertDatabaseCount('menu_items', 0);
        $this->assertDatabaseCount('media', 0);

        Storage::disk('public')->assertMissing($venuePath);
        foreach ($dishPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * Emptying the bin has to reach a child that was *already* marked, which is
     * the normal case: the editor deletes, then permanently deletes. Without
     * `withTrashed()` on the force cascade the soft-delete scope would hide every
     * child and the database's own `ON DELETE CASCADE` would take them with no
     * event fired — orphaning the dish photography for good.
     */
    public function test_emptying_the_bin_purges_media_of_children_already_marked_deleted(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        $dish     = MenuItem::factory()->create(['menu_category_id' => $category->id]);

        $dishPath = $this->attach($dish, 'dish.jpg')->path;

        app(DiningVenueService::class)->destroy($venue);
        $this->assertSoftDeleted($dish);
        $this->assertSame(1, Media::count());

        app(DiningVenueService::class)->forceDestroy($venue);

        $this->assertDatabaseCount('menu_items', 0);
        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($dishPath);
    }

    /**
     * `gallery_items.gallery_category_id` cascades too — the migration already
     * promised these rows would be "cleaned up by the same morph-delete path".
     */
    public function test_permanently_deleting_a_gallery_category_purges_its_photographs(): void
    {
        $category = GalleryCategory::factory()->create();
        $items    = GalleryItem::factory()->count(2)->create(['gallery_category_id' => $category->id]);

        $paths = $items->map(fn (GalleryItem $item): string => $this->attach($item, 'gallery.jpg')->path);

        app(GalleryCategoryService::class)->forceDestroy($category);

        $this->assertDatabaseCount('gallery_items', 0);
        $this->assertDatabaseCount('media', 0);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * The media library places one asset on several parents by COPYING the row,
     * so two rows can name the same `disk` + `path`. Permanently deleting one
     * parent must take only its own row — the other placement is still rendering
     * that file.
     */
    public function test_permanently_deleting_content_leaves_a_file_another_placement_still_shares(): void
    {
        $experience = Experience::factory()->create();
        $promotion  = Promotion::factory()->create();

        $media = $this->attach($experience, 'shared.jpg');
        $path  = $media->path;

        app(MediaService::class)->attachExisting($promotion, [$media->uuid]);

        $this->assertSame(2, Media::count());
        $this->assertSame(1, Media::query()->distinct()->count('path'));

        app(ExperienceService::class)->forceDestroy($experience);

        $this->assertSame(1, Media::count());
        $this->assertCount(1, $promotion->refresh()->images);
        Storage::disk('public')->assertExists($path);

        // Last referent gone → the file goes with it.
        app(PromotionService::class)->forceDestroy($promotion);

        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * The unlink is queued, not inline. One room-type delete can be hundreds of
     * files, and on an object-store disk that is one network round-trip each in
     * a request the editor is waiting on.
     *
     * The row is still removed synchronously — only the storage call is deferred.
     */
    public function test_the_file_unlink_is_queued_rather_than_run_in_the_request(): void
    {
        Queue::fake();

        $experience = Experience::factory()->create();
        $path       = $this->attach($experience)->path;

        app(ExperienceService::class)->forceDestroy($experience);

        $this->assertDatabaseCount('media', 0);

        Queue::assertPushed(
            PurgeMediaFile::class,
            fn (PurgeMediaFile $job): bool => $job->disk === 'public' && $job->path === $path,
        );

        // Nothing touched the disk in-request: that is the worker's work.
        Storage::disk('public')->assertExists($path);
    }

    /**
     * A shared file must not even be *queued* for deletion — otherwise a worker
     * lag long enough for the other placement to be read would still be fine,
     * but a bug in the job's own re-check would become a data loss.
     */
    public function test_no_unlink_is_queued_while_another_row_still_shares_the_file(): void
    {
        $experience = Experience::factory()->create();
        $promotion  = Promotion::factory()->create();

        $media = $this->attach($experience, 'shared.jpg');
        app(MediaService::class)->attachExisting($promotion, [$media->uuid]);

        Queue::fake();

        app(ExperienceService::class)->forceDestroy($experience);

        Queue::assertNothingPushed();
    }

    /**
     * The job is the last line of defence: a placement created between dispatch
     * and execution (the library can copy a row at any time) must survive.
     */
    public function test_the_queued_job_refuses_to_unlink_a_file_that_gained_a_referent(): void
    {
        $promotion = Promotion::factory()->create();
        $media     = $this->attach($promotion, 'kept.jpg');

        (new PurgeMediaFile($media->disk, $media->path))->handle();

        Storage::disk('public')->assertExists($media->path);
    }
}
