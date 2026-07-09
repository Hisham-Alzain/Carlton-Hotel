<?php

namespace Tests\Feature\Cms;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
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

    public function test_admin_can_crud_room(): void
    {
        $token   = $this->editorToken();
        $rt      = RoomType::factory()->create();

        $uuid = $this->withToken($token)->postJson('/api/cms/rooms', [
            'room_type_uuid' => $rt->uuid,
            'number'         => '101',
            'floor'        => 1,
            'is_active'    => true,
        ])->assertStatus(201)->json('data.uuid');

        $this->withToken($token)->getJson("/api/cms/rooms/{$uuid}")->assertOk();

        $this->withToken($token)->putJson("/api/cms/rooms/{$uuid}", ['status' => 'maintenance'])
            ->assertOk()->assertJsonPath('data.status', 'maintenance');

        $this->withToken($token)->deleteJson("/api/cms/rooms/{$uuid}")->assertStatus(204);
    }

    public function test_room_number_must_be_unique(): void
    {
        $rt = RoomType::factory()->create();
        Room::factory()->create(['number' => '101', 'room_type_id' => $rt->id]);

        $this->withToken($this->editorToken())->postJson('/api/cms/rooms', [
            'room_type_uuid' => $rt->uuid,
            'number'         => '101',
        ])->assertStatus(422);
    }

    public function test_staff_without_permission_cannot_manage_rooms(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/cms/rooms')->assertStatus(403);
    }

    public function test_public_index_hides_inactive_rooms(): void
    {
        Room::factory()->create(['is_active' => true]);
        Room::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/rooms')->assertOk();
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_public_show_inactive_room_returns_404(): void
    {
        $room = Room::factory()->create(['is_active' => false]);
        $this->getJson("/api/public/rooms/{$room->uuid}")->assertNotFound();
    }
}
