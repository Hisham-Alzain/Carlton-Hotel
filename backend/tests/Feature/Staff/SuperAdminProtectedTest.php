<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminProtectedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function managerToken(): array
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('staff.manage');
        return [$manager, $manager->createToken('t')->plainTextToken];
    }

    public function test_non_super_admin_cannot_update_super_admin(): void
    {
        [, $token] = $this->managerToken();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->withToken($token)
             ->putJson("/api/staff/{$superAdmin->uuid}", ['name' => 'Hacked'])
             ->assertStatus(403);
    }

    public function test_non_super_admin_cannot_assign_permissions_to_super_admin(): void
    {
        [, $token] = $this->managerToken();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->withToken($token)
             ->postJson("/api/staff/{$superAdmin->uuid}/permissions", ['grant' => ['cms.view']])
             ->assertStatus(403);
    }

    public function test_non_super_admin_cannot_deactivate_super_admin(): void
    {
        [, $token] = $this->managerToken();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->withToken($token)
             ->patchJson("/api/staff/{$superAdmin->uuid}/deactivate")
             ->assertStatus(403);
    }

    public function test_super_admin_can_manage_all_staff(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $token = $superAdmin->createToken('t')->plainTextToken;
        $target = User::factory()->create(['type' => 'staff']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->withToken($token)
             ->putJson("/api/staff/{$target->uuid}", ['name' => 'Updated'])
             ->assertStatus(200);
    }
}
