<?php

namespace Tests\Feature\Cms;

use App\Exceptions\NotFoundException;
use App\Models\RoomType;
use App\Services\Cms\MediaService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `MediaService` used to read `get_class()` on both the write and the ownership
 * check. That agreed with itself, so nothing broke — until a media-bearing
 * model joined `Relation::morphMap()` in `AppServiceProvider` (four models are
 * already aliased there). From that moment every row written through an
 * Eloquent morph relation carries the *alias*, `get_class()` keeps returning
 * the FQCN, and every legitimate delete answers 404.
 *
 * These tests register an alias for a media-bearing model to pin the behaviour
 * before someone adds one for real. The map is process-wide, so it is restored
 * afterwards.
 */
class MediaMorphAliasTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, class-string> */
    private array $originalMorphMap = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->originalMorphMap = Relation::morphMap();
    }

    protected function tearDown(): void
    {
        Relation::morphMap($this->originalMorphMap, false);
        parent::tearDown();
    }

    private function service(): MediaService
    {
        return app(MediaService::class);
    }

    public function test_attach_stores_the_morph_alias_rather_than_the_class_name(): void
    {
        Relation::morphMap(['room_type' => RoomType::class]);

        $media = $this->service()
            ->attach(RoomType::factory()->create(), UploadedFile::fake()->image('photo.jpg'))['data'];

        $this->assertSame('room_type', $media->mediable_type);
    }

    public function test_media_written_through_the_relation_can_still_be_deleted(): void
    {
        Relation::morphMap(['room_type' => RoomType::class]);

        $roomType = RoomType::factory()->create();

        // The morph relation always writes `getMorphClass()`, i.e. the alias.
        // Before the fix the ownership check compared it to the FQCN and 404'd.
        $media = $roomType->images()->create([
            'disk'       => 'public',
            'path'       => 'cms/RoomType/seed.jpg',
            'file_name'  => 'seed.jpg',
            'mime_type'  => 'image/jpeg',
            'size'       => 1024,
            'sort_order' => 0,
        ]);
        Storage::disk('public')->put('cms/RoomType/seed.jpg', 'x');

        $result = $this->service()->destroy($roomType, $media);

        $this->assertSame(204, $result['code']);
        $this->assertDatabaseMissing('media', ['uuid' => $media->uuid]);
    }

    public function test_cross_parent_deletion_is_still_refused_under_an_alias(): void
    {
        Relation::morphMap(['room_type' => RoomType::class]);

        $mine  = RoomType::factory()->create();
        $other = RoomType::factory()->create();

        $media = $mine->images()->create([
            'disk'       => 'public',
            'path'       => 'cms/RoomType/seed.jpg',
            'file_name'  => 'seed.jpg',
            'mime_type'  => 'image/jpeg',
            'size'       => 1024,
            'sort_order' => 0,
        ]);

        $this->expectException(NotFoundException::class);
        $this->service()->destroy($other, $media);
    }

    public function test_attach_and_destroy_agree_when_no_alias_is_registered(): void
    {
        $roomType = RoomType::factory()->create();

        $media = $this->service()
            ->attach($roomType, UploadedFile::fake()->image('photo.jpg'))['data'];

        $this->assertSame(RoomType::class, $media->mediable_type);
        $this->assertSame(204, $this->service()->destroy($roomType, $media)['code']);
    }
}
