<?php

namespace Tests\Feature\Operations;

use App\Contracts\FirebaseServiceInterface;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * @group p10
 */
class OperationsQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    public function test_unified_queue_merges_service_requests_and_tickets(): void
    {
        ServiceRequest::factory()->create();
        Ticket::factory()->create();

        $this->withToken($this->staffToken('service_requests.view', 'tickets.view'))
            ->getJson('/api/operations/queue')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_queue_excludes_a_table_the_caller_cannot_view(): void
    {
        ServiceRequest::factory()->create();
        Ticket::factory()->create();

        $response = $this->withToken($this->staffToken('service_requests.view'))
            ->getJson('/api/operations/queue')
            ->assertOk();

        $response->assertJsonCount(1, 'data.items')
                  ->assertJsonPath('data.items.0.type', 'service_request');
    }

    public function test_queue_sorts_newest_first(): void
    {
        $older = ServiceRequest::factory()->create(['created_at' => now()->subDay()]);
        $newer = Ticket::factory()->create(['created_at' => now()]);

        $this->withToken($this->staffToken('service_requests.view', 'tickets.view'))
            ->getJson('/api/operations/queue')
            ->assertOk()
            ->assertJsonPath('data.items.0.uuid', $newer->uuid)
            ->assertJsonPath('data.items.1.uuid', $older->uuid);
    }

    public function test_queue_requires_view_permission(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/operations/queue')->assertStatus(403);
    }

    public function test_queue_excludes_completed_and_resolved_items(): void
    {
        ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_COMPLETED]);
        Ticket::factory()->create(['status' => Ticket::STATUS_CLOSED]);

        $this->withToken($this->staffToken('service_requests.view', 'tickets.view'))
            ->getJson('/api/operations/queue')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_queue_priority_is_a_consistent_type_across_both_item_types(): void
    {
        ServiceRequest::factory()->create(['priority' => 'high']);
        Ticket::factory()->create(['priority' => 3]);

        $response = $this->withToken($this->staffToken('service_requests.view', 'tickets.view'))
            ->getJson('/api/operations/queue')
            ->assertOk();

        $priorities = collect($response->json('data.items'))->pluck('priority')->all();
        $this->assertEquals(['high', 'high'], $priorities);
    }

    public function test_assigning_a_service_request_updates_and_mirrors(): void
    {
        $fake = $this->fakeFirebase();
        $request = ServiceRequest::factory()->create();
        $assignee = User::factory()->create();

        $this->withToken($this->staffToken('service_requests.assign'))
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/assign", ['user_uuid' => $assignee->uuid])
            ->assertOk()
            ->assertJsonPath('data.assigned_user_uuid', $assignee->uuid);

        $this->assertEquals($assignee->id, $request->fresh()->assigned_user_id);
        $this->assertCount(1, $fake->mirrors);
        $this->assertEquals('ops_queue', $fake->mirrors[0]['collection']);
    }

    public function test_assigning_a_ticket_without_tickets_assign_permission_is_forbidden(): void
    {
        $this->fakeFirebase();
        $ticket = Ticket::factory()->create();
        $assignee = User::factory()->create();

        $this->withToken($this->staffToken('service_requests.assign'))
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/assign", ['user_uuid' => $assignee->uuid])
            ->assertStatus(403);
    }

    public function test_assigning_a_ticket_with_tickets_assign_permission_succeeds(): void
    {
        $this->fakeFirebase();
        $ticket = Ticket::factory()->create();
        $assignee = User::factory()->create();

        $this->withToken($this->staffToken('tickets.assign'))
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/assign", ['user_uuid' => $assignee->uuid])
            ->assertOk();
    }

    public function test_updating_service_request_status_without_permission_is_forbidden(): void
    {
        $this->fakeFirebase();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

        $this->withToken($this->staffToken('service_requests.view'))
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/status", ['status' => ServiceRequest::STATUS_IN_PROGRESS])
            ->assertStatus(403);
    }

    public function test_updating_service_request_status_with_permission_succeeds(): void
    {
        $fake = $this->fakeFirebase();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

        $this->withToken($this->staffToken('service_requests.update'))
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/status", ['status' => ServiceRequest::STATUS_IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceRequest::STATUS_IN_PROGRESS);

        $this->assertCount(1, $fake->mirrors);
    }

    public function test_updating_ticket_status_requires_tickets_respond(): void
    {
        $this->fakeFirebase();
        $ticket = Ticket::factory()->create(['status' => Ticket::STATUS_OPEN]);

        $this->withToken($this->staffToken('tickets.respond'))
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => Ticket::STATUS_RESOLVED])
            ->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_RESOLVED);
    }

    public function test_status_value_is_validated_against_the_items_own_enum(): void
    {
        $this->fakeFirebase();
        $ticket = Ticket::factory()->create();

        $this->withToken($this->staffToken('tickets.respond'))
            ->patchJson("/api/operations/queue/tickets/{$ticket->uuid}/status", ['status' => 'completed'])
            ->assertStatus(422);
    }

    public function test_unknown_type_segment_returns_404(): void
    {
        $this->fakeFirebase();
        $this->withToken($this->staffToken('service_requests.view'))
            ->patchJson('/api/operations/queue/bogus-type/some-uuid/status', ['status' => ServiceRequest::STATUS_NEW])
            ->assertStatus(404);
    }
}
