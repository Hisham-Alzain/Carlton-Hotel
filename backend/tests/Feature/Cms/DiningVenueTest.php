<?php

namespace Tests\Feature\Cms;

use App\Models\DiningVenue;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiningVenueTest extends TestCase
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
            'name'         => ['en' => 'The Grand Restaurant', 'ar' => 'المطعم الكبير'],
            'description'  => ['en' => 'Fine dining', 'ar' => 'مطبخ راقٍ'],
            'cuisine_type' => ['en' => 'International', 'ar' => 'دولي'],
            'hours'        => ['en' => '7am–11pm', 'ar' => '٧ص–١١م'],
            'is_active'    => true,
        ], $overrides);
    }

    public function test_admin_can_crud_dining_venue(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/dining-venues', $this->payload())
            ->assertStatus(201)->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/dining-venues/{$uuid}")->assertOk();

        $this->withToken($token)->putJson("/api/cms/dining-venues/{$uuid}", ['name' => ['en' => 'Bistro', 'ar' => 'بيسترو']])
            ->assertOk()->assertJsonPath('data.name.en', 'Bistro');

        $this->withToken($token)->deleteJson("/api/cms/dining-venues/{$uuid}")->assertStatus(204);
    }

    public function test_staff_without_permission_cannot_manage_dining_venues(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/dining-venues', $this->payload())->assertStatus(403);
    }

    public function test_public_index_hides_inactive_dining_venues(): void
    {
        DiningVenue::factory()->count(2)->create(['is_active' => true]);
        DiningVenue::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/dining-venues')->assertOk();
        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_public_show_inactive_dining_venue_returns_404(): void
    {
        $dv = DiningVenue::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/dining-venues/{$dv->uuid}")->assertNotFound();
    }

    public function test_admin_can_upload_image_to_dining_venue(): void
    {
        Storage::fake('public');
        $dv = DiningVenue::factory()->create();
        $this->withToken($this->editorToken())
            ->postJson("/api/cms/dining-venues/{$dv->uuid}/images", [
                'image' => UploadedFile::fake()->image('restaurant.jpg'),
            ])
            ->assertStatus(201);
        $this->assertDatabaseCount('media', 1);
    }
}
