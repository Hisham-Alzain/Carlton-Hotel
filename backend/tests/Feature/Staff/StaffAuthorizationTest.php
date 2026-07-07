<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_without_manage_gets_403_on_all_staff_endpoints(): void
    {
        $actor = User::factory()->create(); // no staff.manage
        $token = $actor->createToken('t')->plainTextToken;
        $other = User::factory()->create();

        $endpoints = [
            ['GET',   '/api/staff'],
            ['POST',  '/api/staff'],
            ['GET',   "/api/staff/{$other->uuid}"],
            ['PUT',   "/api/staff/{$other->uuid}"],
            ['POST',  "/api/staff/{$other->uuid}/permissions"],
            ['PATCH', "/api/staff/{$other->uuid}/deactivate"],
            ['GET',   '/api/permissions'],
            ['GET',   '/api/roles'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->withToken($token)
                 ->json($method, $uri)
                 ->assertStatus(403, "Expected 403 on $method $uri");
        }
    }
}
