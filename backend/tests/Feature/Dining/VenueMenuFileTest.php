<?php

namespace Tests\Feature\Dining;

use App\Models\DiningVenue;
use App\Models\Media;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DINING-02 staff half (D-07, D-11, D-27, PR-1): one replaceable menu file per
 * venue, invisible to every images path and to the media library.
 */
class VenueMenuFileTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private DiningVenue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        $this->token = $this->permissionToken('cms.view', 'cms.edit');
        $this->venue = DiningVenue::factory()->create();
    }

    private function permissionToken(string ...$permissions): string
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function upload(UploadedFile $file, array $extra = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)
            ->post("/api/cms/dining-venues/{$this->venue->uuid}/menu-file", ['file' => $file] + $extra, ['Accept' => 'application/json']);
    }

    private function remove(?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->deleteJson("/api/cms/dining-venues/{$this->venue->uuid}/menu-file");
    }

    private function pdf(string $name = 'menu.pdf', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function menuRow(): Media
    {
        return Media::where('collection', 'menu')->sole();
    }

    public function test_upload_pdf_creates_the_menu_row(): void
    {
        $this->upload($this->pdf(), ['title' => 'Dinner menu'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Menu file uploaded.')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.title', 'Dinner menu')
            ->assertJsonPath('data.file_name', 'menu.pdf');

        $row = $this->menuRow();
        $this->assertSame($this->venue->getMorphClass(), $row->mediable_type);
        $this->assertSame($this->venue->id, (int) $row->mediable_id);
        Storage::disk('public')->assertExists($row->path);
        $this->assertSame($row->id, $this->venue->fresh()->menuFile->id);
    }

    public function test_upload_jpg_is_accepted(): void
    {
        $this->upload(UploadedFile::fake()->image('menu.jpg'))->assertCreated();

        $this->assertSame('menu', $this->menuRow()->collection);
    }

    public function test_replace_keeps_one_row_and_purges_the_old_file(): void
    {
        $this->upload($this->pdf('a.pdf'))->assertCreated();
        $old = $this->menuRow();

        $this->upload($this->pdf('b.pdf'))->assertCreated();
        $new = $this->menuRow();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('b.pdf', $new->file_name);
        Storage::disk('public')->assertMissing($old->path);
        Storage::disk('public')->assertExists($new->path);
    }

    public function test_replace_keeps_a_file_another_row_still_uses(): void
    {
        $this->upload($this->pdf('a.pdf'))->assertCreated();
        $old = $this->menuRow();
        Media::factory()->create(['disk' => $old->disk, 'path' => $old->path]); // a library copy

        $this->upload($this->pdf('b.pdf'))->assertCreated();

        Storage::disk('public')->assertExists($old->path);
    }

    public function test_delete_removes_the_row_and_file_then_404s(): void
    {
        $this->upload($this->pdf())->assertCreated();
        $row = $this->menuRow();

        $this->remove()->assertOk()->assertJsonPath('message', 'Menu file removed.')->assertJsonPath('data', null);

        $this->assertSame(0, Media::where('collection', 'menu')->count());
        Storage::disk('public')->assertMissing($row->path);

        $this->remove()->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    public function test_delete_removes_all_historical_menu_rows_without_touching_images(): void
    {
        $old = Media::factory()->attachedTo($this->venue)->create([
            'collection' => 'menu', 'path' => 'menus/old.pdf', 'disk' => 'public',
        ]);
        $latest = Media::factory()->attachedTo($this->venue)->create([
            'collection' => 'menu', 'path' => 'menus/latest.pdf', 'disk' => 'public',
        ]);
        $image = Media::factory()->attachedTo($this->venue)->create([
            'path' => 'images/venue.jpg', 'disk' => 'public',
        ]);
        foreach ([$old, $latest, $image] as $row) {
            Storage::disk('public')->put($row->path, 'file');
        }

        $this->remove()->assertOk();

        $this->assertModelMissing($old);
        $this->assertModelMissing($latest);
        $this->assertModelExists($image);
        Storage::disk('public')->assertMissing($old->path);
        Storage::disk('public')->assertMissing($latest->path);
        Storage::disk('public')->assertExists($image->path);
        $this->getJson("/api/public/dining-venues/{$this->venue->uuid}/menu/download")
            ->assertNoContent();
        $this->remove()->assertStatus(404)->assertJsonPath('error_code', 'not_found');
    }

    public static function badUploads(): array
    {
        return [
            'docx'     => [fn () => UploadedFile::fake()->create('menu.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            'too big'  => [fn () => UploadedFile::fake()->create('menu.pdf', 10241, 'application/pdf')],
        ];
    }

    #[DataProvider('badUploads')]
    public function test_invalid_files_are_422(\Closure $file): void
    {
        $this->upload($file())
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['file']);

        $this->assertSame(0, Media::count());
    }

    public function test_missing_file_is_422(): void
    {
        $this->withToken($this->token)
            ->postJson("/api/cms/dining-venues/{$this->venue->uuid}/menu-file", ['title' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_images_paths_never_see_the_menu_row(): void
    {
        $image = Media::factory()->attachedTo($this->venue)->create();
        $this->upload($this->pdf())->assertCreated();
        $menu = $this->menuRow();

        $images = $this->withToken($this->token)->getJson("/api/cms/dining-venues/{$this->venue->uuid}")
            ->assertOk()->json('data.images');
        $this->assertSame([$image->uuid], array_column($images, 'uuid'));

        $this->withToken($this->token)
            ->deleteJson("/api/cms/dining-venues/{$this->venue->uuid}/images/{$menu->uuid}")
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $this->assertModelExists($menu);

        $this->withToken($this->token)
            ->postJson("/api/cms/dining-venues/{$this->venue->uuid}/images/attach", ['media_uuids' => [$menu->uuid]])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');

        $roomType = RoomType::factory()->create();
        $this->withToken($this->token)
            ->postJson("/api/cms/room-types/{$roomType->uuid}/images/attach", ['media_uuids' => [$menu->uuid]])
            ->assertStatus(404)->assertJsonPath('error_code', 'not_found');
        $this->assertSame(1, Media::where('path', $menu->path)->count());

        $library = $this->withToken($this->token)->getJson('/api/cms/media?per_page=100')->assertOk()->json('data.items');
        $this->assertNotContains($menu->uuid, array_column($library, 'uuid'));
        $this->assertContains($image->uuid, array_column($library, 'uuid'));
    }

    public function test_force_deleting_the_venue_purges_the_menu_file(): void
    {
        $this->upload($this->pdf())->assertCreated();
        $row = $this->menuRow();

        $this->withToken($this->token)->deleteJson("/api/cms/dining-venues/{$this->venue->uuid}")->assertSuccessful();
        $this->assertModelExists($row); // a recoverable delete keeps the file

        $purger = $this->permissionToken('cms.view', 'cms.edit', 'cms.purge');
        $this->withToken($purger)->deleteJson("/api/cms/dining-venues/{$this->venue->uuid}/force")->assertSuccessful();

        $this->assertModelMissing($row);
        Storage::disk('public')->assertMissing($row->path);
    }

    public function test_requires_a_token(): void
    {
        $this->post("/api/cms/dining-venues/{$this->venue->uuid}/menu-file", ['file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertStatus(401);
        $this->deleteJson("/api/cms/dining-venues/{$this->venue->uuid}/menu-file")->assertStatus(401);
    }

    public function test_cms_view_alone_is_403(): void
    {
        $viewer = $this->permissionToken('cms.view');

        $this->upload($this->pdf(), [], $viewer)->assertStatus(403);
        $this->remove($viewer)->assertStatus(403);
        $this->assertSame(0, Media::count());
    }
}
