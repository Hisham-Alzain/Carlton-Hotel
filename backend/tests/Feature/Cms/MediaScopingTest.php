<?php

namespace Tests\Feature\Cms;

use App\Models\DiningVenue;
use App\Models\EventSpace;
use App\Models\Facility;
use App\Models\HomeSlider;
use App\Models\MenuItem;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Tests\TestCase;

/**
 * The nested image-delete routes bind {parent} and {media} independently, and
 * the controller used to drop the parent on the floor — so any cms.edit holder
 * could delete any media row through any parent's URL. These tests pin the
 * ownership check on every affected route.
 */
class MediaScopingTest extends TestCase
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
        $user = User::factory()->staff()->create();
        $user->assignRole('content_editor');

        return $user->createToken('t')->plainTextToken;
    }

    private function upload(string $token, string $path): string
    {
        return $this->withToken($token)
            ->postJson($path, ['image' => UploadedFile::fake()->image('photo.jpg')])
            ->assertStatus(201)
            ->json('data.uuid');
    }

    // ── Cross-parent deletion is refused ──────────────────────────────────

    public function test_deleting_media_through_the_wrong_parent_type_fails_and_media_survives(): void
    {
        $token     = $this->editorToken();
        $roomType  = RoomType::factory()->create();
        $promotion = Promotion::factory()->create();

        $promoMedia = $this->upload($token, "/api/cms/promotions/{$promotion->uuid}/images");

        // A promotion's image, addressed through a room-type route.
        $this->withToken($token)
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}/images/{$promoMedia}")
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $this->assertDatabaseHas('media', ['uuid' => $promoMedia]);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_deleting_media_through_a_sibling_of_the_same_type_fails(): void
    {
        $token = $this->editorToken();
        $mine  = RoomType::factory()->create();
        $other = RoomType::factory()->create();

        $media = $this->upload($token, "/api/cms/room-types/{$mine->uuid}/images");

        $this->withToken($token)
            ->deleteJson("/api/cms/room-types/{$other->uuid}/images/{$media}")
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $this->assertDatabaseHas('media', ['uuid' => $media]);
    }

    public function test_cross_parent_deletion_is_refused_on_the_facility_route_too(): void
    {
        $token    = $this->editorToken();
        $facility = Facility::factory()->create();
        $roomType = RoomType::factory()->create();

        $roomTypeMedia = $this->upload($token, "/api/cms/room-types/{$roomType->uuid}/images");

        $this->withToken($token)
            ->deleteJson("/api/cms/facilities/{$facility->uuid}/images/{$roomTypeMedia}")
            ->assertStatus(404);

        $this->assertDatabaseHas('media', ['uuid' => $roomTypeMedia]);
    }

    // ── The correct parent still works ────────────────────────────────────

    public function test_deleting_media_through_its_own_parent_succeeds(): void
    {
        $token    = $this->editorToken();
        $roomType = RoomType::factory()->create();

        $media = $this->upload($token, "/api/cms/room-types/{$roomType->uuid}/images");

        $this->withToken($token)
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}/images/{$media}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('media', ['uuid' => $media]);
    }

    public function test_deleting_promotion_media_through_its_own_parent_succeeds(): void
    {
        $token     = $this->editorToken();
        $promotion = Promotion::factory()->create();

        $media = $this->upload($token, "/api/cms/promotions/{$promotion->uuid}/images");

        $this->withToken($token)
            ->deleteJson("/api/cms/promotions/{$promotion->uuid}/images/{$media}")
            ->assertStatus(204);

        $this->assertDatabaseCount('media', 0);
    }

    // ── The route still requires cms.edit ─────────────────────────────────

    public function test_staff_without_cms_edit_cannot_delete_media(): void
    {
        $roomType = RoomType::factory()->create();

        // Seeded through the relation rather than the API: the upload endpoint
        // needs cms.edit, and a second authenticated request in the same test
        // would be served the first request's memoized guard user.
        $media = $roomType->images()->create([
            'disk'       => 'public',
            'path'       => 'cms/RoomType/seed.jpg',
            'file_name'  => 'seed.jpg',
            'mime_type'  => 'image/jpeg',
            'size'       => 1024,
            'sort_order' => 0,
        ]);

        $outsider = User::factory()->staff()->create();
        $outsider->assignRole('reception');

        $this->withToken($outsider->createToken('t')->plainTextToken)
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}/images/{$media->uuid}")
            ->assertStatus(403);

        $this->assertDatabaseHas('media', ['uuid' => $media->uuid]);
    }

    /**
     * cms.view is read-only: it must not reach the media write routes even
     * though it now reaches every CMS GET.
     */
    public function test_cms_view_alone_cannot_delete_media(): void
    {
        $roomType = RoomType::factory()->create();
        $media    = $roomType->images()->create([
            'disk'       => 'public',
            'path'       => 'cms/RoomType/seed.jpg',
            'file_name'  => 'seed.jpg',
            'mime_type'  => 'image/jpeg',
            'size'       => 1024,
            'sort_order' => 0,
        ]);

        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('cms.view');

        $this->withToken($viewer->createToken('t')->plainTextToken)
            ->deleteJson("/api/cms/room-types/{$roomType->uuid}/images/{$media->uuid}")
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->assertDatabaseHas('media', ['uuid' => $media->uuid]);
    }

    // ── Unauthenticated (401) ─────────────────────────────────────────────

    public function test_unauthenticated_media_upload_is_unauthorized(): void
    {
        $roomType = RoomType::factory()->create();

        $this->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
            'image' => UploadedFile::fake()->image('photo.jpg'),
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_unauthenticated_media_delete_is_unauthorized(): void
    {
        $roomType = RoomType::factory()->create();
        $media    = $roomType->images()->create([
            'disk'       => 'public',
            'path'       => 'cms/RoomType/seed.jpg',
            'file_name'  => 'seed.jpg',
            'mime_type'  => 'image/jpeg',
            'size'       => 1024,
            'sort_order' => 0,
        ]);

        $this->deleteJson("/api/cms/room-types/{$roomType->uuid}/images/{$media->uuid}")
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertDatabaseHas('media', ['uuid' => $media->uuid]);
    }

    // ── Validation (422) ──────────────────────────────────────────────────

    public function test_upload_rejects_a_disallowed_mime_type(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
                'image' => UploadedFile::fake()->create('handbook.pdf', 12, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_rejects_a_file_over_the_size_limit(): void
    {
        $roomType = RoomType::factory()->create();

        // UploadMediaRequest caps uploads at max:5120 (kilobytes).
        $this->withToken($this->editorToken())
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", [
                'image' => UploadedFile::fake()->image('huge.jpg')->size(5121),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_requires_an_image(): void
    {
        $roomType = RoomType::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images", [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('image');
    }

    // ── Every parent route, not just the two that were covered ────────────

    /**
     * MediaController funnels all 8 pairs through one private upload()/delete()
     * helper, so a per-route regression is unlikely — but 5 of the 8 destroy
     * routes had no test at all, and "unlikely to break" is not "covered". The
     * provider walks every registered pair.
     *
     * @return array<string, array{class-string, string}>
     */
    public static function mediaParentProvider(): array
    {
        return [
            'room types'    => [RoomType::class,    'room-types'],
            'rooms'         => [Room::class,        'rooms'],
            'facilities'    => [Facility::class,    'facilities'],
            'dining venues' => [DiningVenue::class, 'dining-venues'],
            'event spaces'  => [EventSpace::class,  'event-spaces'],
            'home sliders'  => [HomeSlider::class,  'home-sliders'],
            'menu items'    => [MenuItem::class,    'menu-items'],
            'promotions'    => [Promotion::class,   'promotions'],
        ];
    }

    #[DataProvider('mediaParentProvider')]
    public function test_media_attaches_and_deletes_through_every_parent_route(string $model, string $segment): void
    {
        $token  = $this->editorToken();
        $parent = $model::factory()->create();
        $base   = "/api/cms/{$segment}/{$parent->uuid}/images";

        $uuid = $this->withToken($token)
            ->postJson($base, ['image' => UploadedFile::fake()->image('photo.jpg')])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message', 'request_id',
                'data' => ['uuid', 'url', 'file_name', 'mime_type', 'size', 'sort_order'],
            ])
            ->json('data.uuid');

        $this->assertDatabaseHas('media', [
            'uuid'           => $uuid,
            'mediable_type'  => $model,
            'mediable_id'    => $parent->id,
        ]);

        $this->withToken($token)
            ->deleteJson("{$base}/{$uuid}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('media', ['uuid' => $uuid]);
    }

    // ── Suite determinism ─────────────────────────────────────────────────

    /**
     * `Storage::fake('public')` only cleans the one disk it is handed, so files
     * written under any other fake-disk root survive the test that made them
     * and are still on disk when the next run starts. That residue is what made
     * this suite report 432/438 on a first run and 438/438 on every run after
     * the directory was deleted by hand.
     *
     * This pair proves the base TestCase purges it: the first test plants
     * residue that nothing else cleans, the second asserts it is gone. Without
     * TestCase::purgeTestingDisks() the second test fails.
     *
     * @return string the planted path, handed to the dependent test
     */
    public function test_a_test_can_leave_residue_under_the_fake_disk_root(): string
    {
        $path = static::fakeDisksRoot()
            .DIRECTORY_SEPARATOR.'stale-disk-from-a-previous-run'
            .DIRECTORY_SEPARATOR.'leftover.bin';

        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'residue');

        $this->assertFileExists($path);

        return $path;
    }

    #[Depends('test_a_test_can_leave_residue_under_the_fake_disk_root')]
    public function test_setup_purges_fake_disk_residue_left_by_a_previous_test(string $path): void
    {
        $this->assertFileDoesNotExist(
            $path,
            'Residue from a previous test survived into this one — Storage::fake() is being '
            .'seeded with stale files, which is what made this suite fail intermittently.',
        );
        $this->assertDirectoryDoesNotExist(dirname($path));
    }
}
