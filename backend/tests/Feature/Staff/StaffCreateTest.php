<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actorWithManage(): User
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('staff.manage');
        return $actor;
    }

    public function test_create_staff_from_preset(): void
    {
        $actor = $this->actorWithManage();
        $token = $actor->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->postJson('/api/staff', [
            'name'     => 'Jane Doe',
            'email'    => 'jane@hotel.com',
            'password' => 'secret123',
            'role'     => 'reception',
        ])->assertStatus(201)->assertJson(['success' => true]);

        $this->assertNotEmpty($res->json('data.uuid'));
        $this->assertSame('staff', $res->json('data.type'));
        $this->assertTrue($res->json('data.is_active'));
        $this->assertContains('reservations.view', $res->json('data.effective_permissions'));
    }

    public function test_staff_without_manage_cannot_create(): void
    {
        $actor = User::factory()->create(); // no staff.manage
        $token = $actor->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/staff', [
            'name' => 'X', 'email' => 'x@x.com', 'password' => 'secret123', 'role' => 'reception',
        ])->assertStatus(403);
    }
}
