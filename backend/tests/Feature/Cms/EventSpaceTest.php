<?php

namespace Tests\Feature\Cms;

use App\Models\EventSpace;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventSpaceTest extends TestCase
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
            'name'        => ['en' => 'Ballroom', 'ar' => 'قاعة الاحتفالات'],
            'description' => ['en' => 'Grand ballroom', 'ar' => 'قاعة احتفالات كبرى'],
            'capacity'    => 300,
            'is_active'   => true,
        ], $overrides);
    }

    public function test_admin_can_crud_event_space(): void
    {
        $token = $this->editorToken();

        $uuid = $this->withToken($token)->postJson('/api/cms/event-spaces', $this->payload())
            ->assertStatus(201)->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/event-spaces/{$uuid}")->assertOk();

        $this->withToken($token)->putJson("/api/cms/event-spaces/{$uuid}", ['capacity' => 500])
            ->assertOk()->assertJsonPath('data.capacity', 500);

        $this->withToken($token)->deleteJson("/api/cms/event-spaces/{$uuid}")->assertStatus(204);
    }

    public function test_staff_without_permission_cannot_manage_event_spaces(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/cms/event-spaces', $this->payload())->assertStatus(403);
    }

    public function test_public_index_hides_inactive_event_spaces(): void
    {
        EventSpace::factory()->create(['is_active' => true]);
        EventSpace::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/event-spaces')->assertOk();
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_public_show_inactive_event_space_returns_404(): void
    {
        $es = EventSpace::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/event-spaces/{$es->uuid}")->assertNotFound();
    }

    public function test_admin_can_upload_image_to_event_space(): void
    {
        Storage::fake('public');
        $es = EventSpace::factory()->create();
        $this->withToken($this->editorToken())
            ->postJson("/api/cms/event-spaces/{$es->uuid}/images", [
                'image' => UploadedFile::fake()->image('ballroom.jpg'),
            ])
            ->assertStatus(201);
        $this->assertDatabaseCount('media', 1);
    }
}
