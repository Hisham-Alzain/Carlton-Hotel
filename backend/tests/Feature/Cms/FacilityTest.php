<?php

namespace Tests\Feature\Cms;

use App\Models\Facility;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacilityTest extends TestCase
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
            'name'        => ['en' => 'Swimming Pool', 'ar' => 'حمام السباحة'],
            'description' => ['en' => 'Outdoor pool', 'ar' => 'مسبح خارجي'],
            'location'    => ['en' => 'Ground Floor', 'ar' => 'الطابق الأرضي'],
            'hours'       => ['en' => '6am–10pm', 'ar' => '٦ص–١٠م'],
            'is_active'   => true,
        ], $overrides);
    }

    public function test_admin_can_crud_facility(): void
    {
        $token = $this->editorToken();

        $created = $this->withToken($token)->postJson('/api/cms/facilities', $this->payload())
            ->assertStatus(201)->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/facilities/{$created}")->assertOk();

        $this->withToken($token)->putJson("/api/cms/facilities/{$created}", ['name' => ['en' => 'Pool', 'ar' => 'مسبح']])
            ->assertOk()->assertJsonPath('data.name.en', 'Pool');

        $this->withToken($token)->deleteJson("/api/cms/facilities/{$created}")->assertStatus(204);
    }

    public function test_staff_without_permission_cannot_manage_facilities(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/facilities', $this->payload())->assertStatus(403);
    }

    public function test_public_index_hides_inactive_facilities(): void
    {
        Facility::factory()->create(['is_active' => true]);
        Facility::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/facilities')->assertOk();
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_public_show_inactive_facility_returns_404(): void
    {
        $f = Facility::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/facilities/{$f->uuid}")->assertNotFound();
    }

    public function test_admin_can_upload_image_to_facility(): void
    {
        Storage::fake('public');
        $f = Facility::factory()->create();
        $this->withToken($this->editorToken())
            ->postJson("/api/cms/facilities/{$f->uuid}/images", [
                'image' => UploadedFile::fake()->image('pool.jpg'),
            ])
            ->assertStatus(201);
        $this->assertDatabaseCount('media', 1);
    }
}
