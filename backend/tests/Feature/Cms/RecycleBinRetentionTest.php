<?php

namespace Tests\Feature\Cms;

use App\Models\DiningVenue;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\RecycleBin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The bin has to empty itself.
 *
 * `RecycleBinTest` proved a delete is recoverable and that `…/force` can make it
 * permanent. Nothing ever called `…/force` on the bin's behalf, so every deleted
 * record kept its row, its `media` rows and its files indefinitely: storage grew
 * without bound and "recoverable" quietly meant "permanent". `cms:purge-bin` is
 * the retention half.
 *
 * Three things have to hold:
 *
 * 1. The window is a window — old rows go, recent ones stay, and where the line
 *    sits is configuration rather than a literal in the command.
 * 2. The purge runs through Eloquent. This is the whole point of the exercise:
 *    a bulk `delete from …` would clear the tables at ten times the speed and
 *    strand every photograph on the disk, because `PurgesMedia` and
 *    `CascadesSoftDeletes` are model events and a mass delete fires none.
 * 3. An operator can see it coming (`--dry-run`), trigger it by hand, and find
 *    out afterwards what it took (the log).
 */
class RecycleBinRetentionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every model that holds recoverable rows, pinned by name.
     *
     * The command discovers its own targets — a hardcoded list would be the
     * second copy that drifts — but discovery is "everything under `App\Models`
     * using `SoftDeletes`", and that is only safe while soft-deleting means
     * "editorial content in a recycle bin". The day a financial record gains
     * `SoftDeletes` for an audit trail, this assertion goes red and somebody has
     * to decide whether a retention job may destroy it, instead of finding out
     * ninety days later.
     *
     * @var list<class-string>
     */
    private const SOFT_DELETABLE = [
        \App\Models\Amenity::class,
        \App\Models\DiningVenue::class,
        \App\Models\EventSpace::class,
        \App\Models\Experience::class,
        \App\Models\Facility::class,
        \App\Models\Faq::class,
        \App\Models\GalleryCategory::class,
        \App\Models\GalleryItem::class,
        \App\Models\HomeSlider::class,
        \App\Models\JournalPost::class,
        \App\Models\MenuCategory::class,
        \App\Models\MenuItem::class,
        \App\Models\Page::class,
        \App\Models\Promotion::class,
        \App\Models\Room::class,
        \App\Models\RoomType::class,
        \App\Models\SiteSetting::class,
        \App\Models\Testimonial::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    // ── 1. The window ─────────────────────────────────────────────────────

    public function test_the_purge_destroys_records_binned_longer_than_the_window(): void
    {
        $page = $this->binnedDaysAgo(Page::factory()->create(), 120);

        $this->assertSame(1, Page::onlyTrashed()->count());

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertSame(0, Page::withTrashed()->count());
    }

    public function test_the_purge_leaves_records_still_inside_the_window(): void
    {
        $recent = $this->binnedDaysAgo(Page::factory()->create(), 30);
        $live   = Page::factory()->create();

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertNotNull($recent->fresh()->deleted_at, 'still recoverable');
        $this->assertNull($live->fresh()->deleted_at, 'never touched a live row');
        $this->assertSame(2, Page::withTrashed()->count());
    }

    /**
     * The default is not an implementation detail — it is the promise the API
     * contract makes about how long an editor has to notice a mistake. Ninety
     * days is one editorial quarter plus the review that closes it; see the
     * reasoning in `config/cms.php`.
     */
    public function test_the_default_window_is_ninety_days(): void
    {
        $this->assertSame(90, config('cms.recycle_bin.retention_days'));

        $justInside  = $this->binnedDaysAgo(Page::factory()->create(['slug' => 'inside']), 89);
        $justOutside = $this->binnedDaysAgo(Page::factory()->create(['slug' => 'outside']), 91);

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertNotNull($justInside->fresh()?->deleted_at);
        $this->assertFalse($this->stillExists($justOutside));
    }

    public function test_the_window_is_read_from_config_not_hardcoded(): void
    {
        config(['cms.recycle_bin.retention_days' => 10]);

        $old   = $this->binnedDaysAgo(Page::factory()->create(['slug' => 'old']), 11);
        $young = $this->binnedDaysAgo(Page::factory()->create(['slug' => 'young']), 9);

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertFalse($this->stillExists($old), 'past the configured window and still here');
        $this->assertTrue($this->stillExists($young), 'inside the configured window and gone');
    }

    public function test_the_days_option_overrides_the_configured_window(): void
    {
        $page = $this->binnedDaysAgo(Page::factory()->create(), 30);

        $this->artisan('cms:purge-bin', ['--days' => 200])->assertSuccessful();
        $this->assertNotNull($page->fresh()->deleted_at);

        $this->artisan('cms:purge-bin', ['--days' => 7])->assertSuccessful();
        $this->assertFalse($this->stillExists($page));
    }

    public function test_a_nonsensical_window_is_rejected_rather_than_purging_everything(): void
    {
        $page = $this->binnedDaysAgo(Page::factory()->create(), 400);

        $this->artisan('cms:purge-bin', ['--days' => 0])->assertExitCode(2);

        $this->assertNotNull($page->fresh()->deleted_at);
    }

    // ── 2. Through Eloquent, so the files actually go ─────────────────────

    /**
     * The reason this is a command walking rows and not one `delete from` per
     * table. `PurgesMedia` hooks `forceDeleted` and `Media`'s own delete hook
     * unlinks the file; bulk SQL fires neither, and the photography of every
     * purged record would stay on the disk with nothing pointing at it — the
     * orphaning the traits exist to stop, at the scale of the whole bin.
     */
    public function test_the_purge_takes_the_media_rows_and_the_stored_files(): void
    {
        $roomType = RoomType::factory()->create();

        $mediaUuid = $this->withToken($this->editorToken())
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
                'image' => UploadedFile::fake()->image('suite.jpg'),
            ])
            ->assertStatus(201)
            ->json('data.uuid');

        $path = Media::where('uuid', $mediaUuid)->value('path');
        Storage::disk('public')->assertExists($path);

        $this->binnedDaysAgo($roomType, 120);

        // Kept while recoverable — that is what a restore hands back.
        $this->assertDatabaseHas('media', ['uuid' => $mediaUuid]);
        Storage::disk('public')->assertExists($path);

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertDatabaseMissing('media', ['uuid' => $mediaUuid]);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * A venue leaving the bin has to take its categories and every dish with it,
     * and each of them has to go through Eloquent rather than through the
     * database's own `ON DELETE CASCADE`.
     *
     * The rows would vanish either way — which is what makes this trap so easy
     * to fall into. The dish's photograph is the tell: `PurgesMedia` hooks
     * `forceDeleted`, so a descendant taken by the database fires nothing and
     * strands its file on the disk two levels down from the row anybody looked
     * at.
     */
    public function test_the_purge_takes_the_cascade_descendants_and_their_media(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        $dishes   = MenuItem::factory()->count(3)->create(['menu_category_id' => $category->id]);

        $media = Media::factory()->attachedTo($dishes->first())->create();
        Storage::disk('public')->put($media->path, 'jpeg-bytes');

        $this->binnedDaysAgo($venue, 120);

        $this->assertSame(3, MenuItem::onlyTrashed()->count());

        $this->artisan('cms:purge-bin')->assertSuccessful();

        $this->assertSame(0, DiningVenue::withTrashed()->count());
        $this->assertSame(0, MenuCategory::withTrashed()->count());
        $this->assertSame(0, MenuItem::withTrashed()->count());

        $this->assertDatabaseMissing('media', ['uuid' => $media->uuid]);
        Storage::disk('public')->assertMissing($media->path);
    }

    /**
     * Leaves before roots. A dish and the venue above it carry the same
     * `deleted_at` and leave the window together; purging the venue first would
     * take the dish with it and the dish's own pass would then report a smaller
     * number than the dry run promised for the same bin.
     */
    public function test_deeper_models_are_purged_before_the_parents_that_would_cascade_over_them(): void
    {
        $order = array_flip(RecycleBin::modelsWithBin());

        $this->assertLessThan($order[MenuCategory::class], $order[MenuItem::class]);
        $this->assertLessThan($order[DiningVenue::class], $order[MenuCategory::class]);
        $this->assertLessThan($order[RoomType::class], $order[Room::class]);
        $this->assertLessThan($order[GalleryCategory::class], $order[GalleryItem::class]);
    }

    public function test_every_soft_deletable_model_is_covered_by_the_purge(): void
    {
        $covered = RecycleBin::modelsWithBin();

        $this->assertEqualsCanonicalizing(
            self::SOFT_DELETABLE,
            $covered,
            'A model gained or lost SoftDeletes. Decide whether a retention job may destroy it.',
        );

        foreach ($covered as $class) {
            $this->assertContains(
                SoftDeletes::class,
                class_uses_recursive($class),
                "{$class} has no recoverable delete and must not be walked by the purge.",
            );
        }
    }

    // ── 3. What an operator can see and do ────────────────────────────────

    public function test_a_dry_run_reports_what_would_go_and_destroys_nothing(): void
    {
        $page     = $this->binnedDaysAgo(Page::factory()->create(), 120);
        $roomType = $this->binnedDaysAgo(RoomType::factory()->create(), 120);

        $this->artisan('cms:purge-bin', ['--dry-run' => true])
            ->expectsOutputToContain('page: 1')
            ->expectsOutputToContain('room_type: 1')
            ->expectsOutputToContain('Would purge 2 record(s)')
            ->assertSuccessful();

        $this->assertNotNull($page->fresh()->deleted_at);
        $this->assertNotNull($roomType->fresh()->deleted_at);
        $this->assertSame(2, Page::withTrashed()->count() + RoomType::withTrashed()->count());
    }

    public function test_a_dry_run_counts_exactly_what_the_real_run_then_destroys(): void
    {
        $venue    = DiningVenue::factory()->create();
        $category = MenuCategory::factory()->forVenue($venue)->create();
        MenuItem::factory()->count(2)->create(['menu_category_id' => $category->id]);

        $this->binnedDaysAgo($venue, 120);

        Log::spy();

        $this->artisan('cms:purge-bin', ['--dry-run' => true])->assertSuccessful();
        $this->artisan('cms:purge-bin')->assertSuccessful();

        $counts = [];

        Log::shouldHaveReceived('info')
            ->twice()
            ->withArgs(function (string $message, array $context) use (&$counts): bool {
                $counts[] = $context['by_type'];

                return true;
            });

        $this->assertSame($counts[0], $counts[1], 'the dry run promised a different number than the purge delivered');
        $this->assertSame(['menu_item' => 2, 'menu_category' => 1, 'dining_venue' => 1], $counts[1]);
    }

    /**
     * A scheduled job that destroys content and says nothing makes "where did
     * that page go?" unanswerable.
     */
    public function test_the_purge_logs_what_it_destroyed(): void
    {
        $this->binnedDaysAgo(Page::factory()->create(), 120);

        Log::spy();

        $this->artisan('cms:purge-bin')->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Recycle bin purged'
                && $context['dry_run'] === false
                && $context['retention_days'] === 90
                && $context['total'] === 1
                && $context['by_type'] === ['page' => 1]
                && is_string($context['cutoff']));
    }

    public function test_a_dry_run_is_logged_as_a_dry_run(): void
    {
        $this->binnedDaysAgo(Page::factory()->create(), 120);

        Log::spy();

        $this->artisan('cms:purge-bin', ['--dry-run' => true])->assertSuccessful();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool =>
                $message === 'Recycle bin purge (dry run)' && $context['dry_run'] === true);
    }

    public function test_an_empty_bin_is_reported_rather_than_silently_doing_nothing(): void
    {
        Page::factory()->create();

        $this->artisan('cms:purge-bin')
            ->expectsOutputToContain('Nothing in the recycle bin is older than 90 day(s).')
            ->assertSuccessful();
    }

    public function test_the_purge_is_registered_on_the_scheduler(): void
    {
        $scheduled = array_map(
            fn ($event): string => $event->command ?? '',
            app(Schedule::class)->events(),
        );

        $matching = array_values(array_filter(
            $scheduled,
            fn (string $command): bool => str_contains($command, 'cms:purge-bin'),
        ));

        $this->assertCount(
            1,
            $matching,
            'Nothing empties the bin unless the scheduler runs it: '.implode(' | ', $scheduled),
        );
    }

    public function test_the_purge_chunks_rather_than_loading_the_whole_bin(): void
    {
        Page::factory()->count(7)->create();

        $this->travelTo(now()->subDays(120));
        Page::query()->get()->each->delete();
        $this->travelBack();

        $this->artisan('cms:purge-bin', ['--chunk' => 2])->assertSuccessful();

        $this->assertSame(0, Page::withTrashed()->count());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Put a record in the bin as of `$days` ago, through the model so the
     * cascade stamps its descendants with the same `deleted_at` the real delete
     * would have given them.
     */
    private function binnedDaysAgo(\Illuminate\Database\Eloquent\Model $model, int $days): \Illuminate\Database\Eloquent\Model
    {
        $this->travelTo(now()->subDays($days));
        $model->delete();
        $this->travelBack();

        return $model;
    }

    /**
     * Whether the row is still in its table at all, binned or not. A boolean
     * rather than `assertNull(...->find($id))`, which dumps a whole hydrated
     * model into the failure message when it is the presence that matters.
     */
    private function stillExists(\Illuminate\Database\Eloquent\Model $model): bool
    {
        return $model::withTrashed()->whereKey($model->getKey())->exists();
    }

    private function editorToken(): string
    {
        $user = User::factory()->staff()->create();
        $user->givePermissionTo(['cms.view', 'cms.edit', 'cms.restore']);

        return $user->createToken('t')->plainTextToken;
    }
}
