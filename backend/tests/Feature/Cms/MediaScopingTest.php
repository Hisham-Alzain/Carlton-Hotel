<?php

namespace Tests\Feature\Cms;

use App\Models\Facility;
use App\Models\Promotion;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
