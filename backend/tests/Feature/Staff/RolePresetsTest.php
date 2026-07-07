<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePresetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_roles_returns_5_presets_with_permissions(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('staff.manage');
        $token = $actor->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/roles')
                    ->assertStatus(200)->assertJson(['success' => true]);

        $roles = $res->json('data');
        $this->assertCount(5, $roles);

        $reception = collect($roles)->firstWhere('name', 'reception');
        $this->assertNotNull($reception);
        $this->assertContains('reservations.view', $reception['permissions']);
        $this->assertContains('folios.settle', $reception['permissions']);
    }
}
