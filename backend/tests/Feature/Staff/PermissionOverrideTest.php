<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_per_account_permission_override(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(['staff.manage', 'cms.edit']); // actor holds cms.edit
        $token = $actor->createToken('t')->plainTextToken;

        // Create target staff from reception preset
        $target = User::factory()->create(['type' => 'staff']);
        $target->assignRole('reception'); // gets reservations.view etc, NOT cms.edit

        $this->assertNotContains('cms.edit', $target->getAllPermissions()->pluck('name')->toArray());

        // Grant cms.edit (actor holds it — allowed)
        $this->withToken($token)->postJson("/api/staff/{$target->uuid}/permissions", [
            'grant' => ['cms.edit'],
        ])->assertStatus(200);

        $target->refresh()->load('roles', 'permissions');
        $this->assertContains('cms.edit', $target->getAllPermissions()->pluck('name')->toArray());

        // Revoke the direct grant
        $this->withToken($token)->postJson("/api/staff/{$target->uuid}/permissions", [
            'revoke' => ['cms.edit'],
        ])->assertStatus(200);

        $target->refresh()->load('roles', 'permissions');
        $this->assertNotContains('cms.edit', $target->getDirectPermissions()->pluck('name')->toArray());
    }
}
