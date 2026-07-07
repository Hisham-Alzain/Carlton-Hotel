<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardsResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_token_hits_staff_probe(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/probe/staff')
             ->assertStatus(200)
             ->assertJson(['success' => true]);
    }

    public function test_guest_token_hits_guest_probe(): void
    {
        $guest = Guest::factory()->create();
        $token = $guest->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/probe/guest')
             ->assertStatus(200)
             ->assertJson(['success' => true]);
    }

    public function test_staff_token_rejected_on_guest_probe(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/probe/guest')
             ->assertStatus(401);
    }

    public function test_unauthenticated_returns_401_with_error_code(): void
    {
        $this->getJson('/api/probe/staff')
             ->assertStatus(401)
             ->assertJson(['error_code' => 'unauthorized']);
    }
}
