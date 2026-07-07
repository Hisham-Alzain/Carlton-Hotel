<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_returns_permissions_and_type(): void
    {
        $user = User::factory()->create(['email'=>'staff@hotel.com','password'=>bcrypt('secret')]);
        $user->assignRole('reception');

        $res = $this->postJson('/api/auth/login', ['email'=>'staff@hotel.com','password'=>'secret'])
                    ->assertStatus(200)->assertJson(['success'=>true]);

        $this->assertArrayHasKey('permissions', $res->json('data'));
        $this->assertNotEmpty($res->json('data.permissions'));
        $this->assertArrayHasKey('type', $res->json('data.user'));
    }

    public function test_invalid_credentials_returns_401(): void
    {
        User::factory()->create(['email'=>'a@b.com','password'=>bcrypt('right')]);
        $this->postJson('/api/auth/login', ['email'=>'a@b.com','password'=>'wrong'])
             ->assertStatus(401)->assertJson(['error_code'=>'unauthorized']);
    }

    public function test_inactive_user_returns_403(): void
    {
        User::factory()->create(['email'=>'inactive@hotel.com','password'=>bcrypt('secret'),'is_active'=>false]);
        $this->postJson('/api/auth/login', ['email'=>'inactive@hotel.com','password'=>'secret'])
             ->assertStatus(403)->assertJson(['error_code'=>'forbidden']);
    }

    public function test_me_returns_user_resource(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')
             ->assertStatus(200)->assertJsonPath('data.uuid', $user->uuid);
    }

    public function test_logout_invalidates_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/logout')->assertStatus(200);
        // Flush cached auth guard so next request re-resolves from DB (token now deleted)
        $this->app->get('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
    }
}
