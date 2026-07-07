<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivatedCannotAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('staff.manage');
        $mgrToken = $manager->createToken('t')->plainTextToken;

        $target = User::factory()->create([
            'email'    => 'target@hotel.com',
            'password' => 'secret123',   // cast will hash this
            'is_active'=> true,
        ]);

        // Deactivate
        $this->withToken($mgrToken)
             ->patchJson("/api/staff/{$target->uuid}/deactivate")
             ->assertStatus(200);

        // Try to login
        $this->postJson('/api/auth/login', [
            'email'    => 'target@hotel.com',
            'password' => 'secret123',
        ])->assertStatus(403)->assertJson(['error_code' => 'forbidden']);
    }

    public function test_deactivated_user_existing_token_rejected(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('staff.manage');
        $mgrToken = $manager->createToken('t')->plainTextToken;

        $target = User::factory()->create(['is_active' => true]);
        $targetToken = $target->createToken('t')->plainTextToken;

        // Deactivate (also deletes tokens)
        $this->withToken($mgrToken)
             ->patchJson("/api/staff/{$target->uuid}/deactivate")
             ->assertStatus(200);

        $this->app->get('auth')->forgetGuards();

        $this->withToken($targetToken)
             ->getJson('/api/auth/me')
             ->assertStatus(401);
    }
}
