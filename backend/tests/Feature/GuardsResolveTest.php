<?php
namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardsResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_token_accesses_me_endpoint(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')
             ->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_guest_token_rejected_on_staff_endpoint(): void
    {
        $guest = Guest::factory()->create();
        $token = $guest->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/auth/me')
             ->assertStatus(401)->assertJson(['error_code' => 'unauthorized']);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/auth/me')
             ->assertStatus(401)->assertJson(['error_code' => 'unauthorized']);
    }
}
