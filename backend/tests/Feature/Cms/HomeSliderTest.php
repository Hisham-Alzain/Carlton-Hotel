<?php

namespace Tests\Feature\Cms;

use App\Models\HomeSlider;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeSliderTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'header_text'      => ['en' => 'Timeless Hospitality', 'ar' => 'ضيافة خالدة'],
            'location'         => ['en' => 'Damascus, Syria', 'ar' => 'دمشق، سوريا'],
            'description_text' => ['en' => 'A landmark address in the heart of the city.', 'ar' => 'عنوان مميز في قلب المدينة.'],
            'is_active'        => true,
            'sort_order'       => 0,
        ], $overrides);
    }

    // ── Admin CRUD ────────────────────────────────────────────────────────

    public function test_admin_can_create_and_read_back_a_slider(): void
    {
        $res = $this->withToken($this->editorToken())
            ->postJson('/api/cms/home-sliders', $this->payload())
            ->assertStatus(201);

        $this->assertSame('Timeless Hospitality', $res->json('data.header_text.en'));
        $this->assertSame('دمشق، سوريا', $res->json('data.location.ar'));
        $this->assertNotEmpty($res->json('data.description_text.en'));
    }

    public function test_admin_can_list_update_and_delete_sliders(): void
    {
        $token  = $this->editorToken();
        $slider = HomeSlider::factory()->create();

        $this->withToken($token)->getJson('/api/cms/home-sliders')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['items', 'meta']]);

        $this->withToken($token)
            ->putJson("/api/cms/home-sliders/{$slider->uuid}", ['sort_order' => 5])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 5);

        $this->withToken($token)->deleteJson("/api/cms/home-sliders/{$slider->uuid}")->assertStatus(204);
        $this->assertDatabaseMissing('home_sliders', ['id' => $slider->id]);
    }

    public function test_create_requires_bilingual_fields(): void
    {
        $this->withToken($this->editorToken())
            ->postJson('/api/cms/home-sliders', $this->payload(['header_text' => ['en' => 'Only English']]))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    // ── Permission gate ───────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/cms/home-sliders')->assertStatus(401);
        $this->postJson('/api/cms/home-sliders', [])->assertStatus(401);
    }

    public function test_staff_without_cms_edit_cannot_write(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/home-sliders', $this->payload())->assertStatus(403);
    }

    // ── Public endpoint ───────────────────────────────────────────────────

    public function test_public_index_returns_active_sliders_in_sort_order(): void
    {
        HomeSlider::factory()->create(['sort_order' => 1]);
        HomeSlider::factory()->create(['sort_order' => 0]);
        HomeSlider::factory()->inactive()->create();

        $res = $this->getJson('/api/public/home-sliders')->assertOk();

        $items = $res->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame(0, $items[0]['sort_order']);
        $this->assertSame(1, $items[1]['sort_order']);
    }

    // ── Photo ─────────────────────────────────────────────────────────────

    public function test_uploaded_image_surfaces_as_the_photo_field(): void
    {
        Storage::fake('public');
        $slider = HomeSlider::factory()->create();

        $this->withToken($this->editorToken())
            ->postJson("/api/cms/home-sliders/{$slider->uuid}/images", [
                'image' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
            ])
            ->assertStatus(201);

        $res = $this->getJson('/api/public/home-sliders')->assertOk();
        $this->assertNotEmpty($res->json('data.items.0.photo'));
    }

    public function test_photo_is_null_before_any_upload(): void
    {
        HomeSlider::factory()->create();

        $res = $this->getJson('/api/public/home-sliders')->assertOk();
        $this->assertNull($res->json('data.items.0.photo'));
    }
}
