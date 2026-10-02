<?php

namespace Tests\Feature\Operations;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * Council fact-resolution regression (Phase 7, D-27): deactivating a staff
 * account revokes its tokens, so a leftover bearer token cannot claim queue
 * work. This is why no per-request EnsureUserIsActive middleware is added.
 */
class DeactivatedTokenClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService());
    }

    private function responder(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('tickets.respond');

        return $user;
    }

    public function test_deactivated_users_old_token_gets_401_on_claim(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('staff.manage');
        $target      = $this->responder();
        $targetToken = $target->createToken('t')->plainTextToken;
        $ticket      = Ticket::factory()->create();

        $this->withToken($manager->createToken('t')->plainTextToken)
            ->patchJson("/api/staff/{$target->uuid}/deactivate")
            ->assertOk();

        $this->assertSame(0, $target->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($targetToken)
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/claim")
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error_code' => 'unauthorized']);

        $this->assertNull($ticket->fresh()->assigned_user_id);
    }

    public function test_active_token_can_claim(): void
    {
        $target = $this->responder();
        $ticket = Ticket::factory()->create();

        $this->withToken($target->createToken('t')->plainTextToken)
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/claim")
            ->assertOk()
            ->assertJsonPath('data.assigned_user_uuid', $target->uuid);
    }
}
