<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsGroupedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permissions_grouped_by_module(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('staff.manage');
        $token = $actor->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/permissions')
                    ->assertStatus(200)->assertJson(['success' => true]);

        $groups = $res->json('data');
        $this->assertCount(8, $groups);

        $srGroup = collect($groups)->firstWhere('module', 'service_requests');
        $this->assertNotNull($srGroup, 'service_requests group exists');
        $this->assertCount(3, $srGroup['permissions']);
        $this->assertSame('service_requests', $srGroup['module']);
    }
}
