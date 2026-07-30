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
use App\Services\Cms\MediaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deleting content must take its media rows — and the files behind them — with
 * it.
 *
 * Before `PurgesMedia`, every CMS delete left both behind forever: the rows
 * pointed at a parent that no longer existed and nothing ever unlinked the
 * files. The cascade paths were the worst of it, because a single room type or
 * dining venue takes a whole subtree of media with it and none of those child
 * deletes pass through a service at all — the database does them.
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

    public function test_deleting_content_purges_its_media_rows_and_files(): void
    {
        $experience = Experience::factory()->create();
        $path       = $this->attach($experience)->path;

        Storage::disk('public')->assertExists($path);

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/experiences/{$experience->uuid}")
            ->assertStatus(204);

        $this->assertDatabaseCount('media', 0);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * `rooms.room_type_id` is `ON DELETE CASCADE`, so the rooms vanish without
     * Eloquent firing a single event for them and `RoomService::destroy()` is
     * never called. Their photography is only reachable from the room type's own
     * `deleting` hook — which is why the cleanup cannot live in a service.
     */
    public function test_deleting_a_room_type_purges_the_images_of_the_rooms_it_cascades_to(): void
    {
        $roomType = RoomType::factory()->create();
        $rooms    = Room::factory()->count(2)->create(['room_type_id' => $roomType->id]);

        $typePath  = $this->attach($roomType, 'suite.jpg')->path;
        $roomPaths = $rooms->map(fn (Room $room): string => $this->attach($room, 'room.jpg')->path);

        $this->assertSame(3, Media::count());

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}")
            ->assertStatus(204);

        // The database cascade really did fire — otherwise this test would pass
        // for the wrong reason.
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
    public function test_deleting_a_dining_venue_purges_its_menu_categories_and_dish_images(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        $dishes   = MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id]);

        $venuePath  = $this->attach($venue, 'venue.jpg')->path;
        $dishPaths  = $dishes->map(fn (MenuItem $dish): string => $this->attach($dish, 'dish.jpg')->path);

        $this->assertSame(3, Media::count());

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/dining-venues/{$venue->uuid}")
            ->assertStatus(204);

        $this->assertDatabaseCount('menu_categories', 0);
        $this->assertDatabaseCount('menu_items', 0);
        $this->assertDatabaseCount('media', 0);

        Storage::disk('public')->assertMissing($venuePath);
        foreach ($dishPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * `gallery_items.gallery_category_id` cascades too — the migration already
     * promised these rows would be "cleaned up by the same morph-delete path".
     */
    public function test_deleting_a_gallery_category_purges_its_photographs(): void
    {
        $category = GalleryCategory::factory()->create();
        $items    = GalleryItem::factory()->count(2)->create(['gallery_category_id' => $category->id]);

        $paths = $items->map(fn (GalleryItem $item): string => $this->attach($item, 'gallery.jpg')->path);

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/gallery-categories/{$category->uuid}")
            ->assertStatus(204);

        $this->assertDatabaseCount('gallery_items', 0);
        $this->assertDatabaseCount('media', 0);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    /**
     * The media library places one asset on several parents by COPYING the row,
     * so two rows can name the same `disk` + `path`. Deleting one parent must
     * take only its own row — the other placement is still rendering that file.
     */
    public function test_deleting_content_leaves_a_file_another_placement_still_shares(): void
    {
        $experience = Experience::factory()->create();
        $promotion  = Promotion::factory()->create();

        $media = $this->attach($experience, 'shared.jpg');
        $path  = $media->path;

        app(MediaService::class)->attachExisting($promotion, [$media->uuid]);

        $this->assertSame(2, Media::count());
        $this->assertSame(1, Media::query()->distinct()->count('path'));

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/experiences/{$experience->uuid}")
            ->assertStatus(204);

        $this->assertSame(1, Media::count());
        $this->assertCount(1, $promotion->refresh()->images);
        Storage::disk('public')->assertExists($path);

        // Last referent gone → the file goes with it.
        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/promotions/{$promotion->uuid}")
            ->assertStatus(204);

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

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/experiences/{$experience->uuid}")
            ->assertStatus(204);

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

        $this->withToken($this->editorToken())
            ->deleteJson("/api/cms/experiences/{$experience->uuid}")
            ->assertStatus(204);

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
