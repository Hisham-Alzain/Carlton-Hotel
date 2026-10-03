<?php
namespace Tests\Feature\Staff;

use App\Contracts\FirebaseServiceInterface;
use App\Models\Conversation;
use App\Models\EventInquiry;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeFirebaseService;
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

    public function test_roles_returns_7_presets_with_permissions(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('staff.manage');
        $token = $actor->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/roles')
                    ->assertStatus(200)->assertJson(['success' => true]);

        $roles = $res->json('data');
        $this->assertCount(7, $roles);

        $reception = collect($roles)->firstWhere('name', 'reception');
        $this->assertNotNull($reception);
        $this->assertContains('reservations.view', $reception['permissions']);
        $this->assertContains('folios.settle', $reception['permissions']);
        // Phase 6 (D-06): reception reads, creates and assigns tasks but does not move them.
        $this->assertContains('housekeeping.assign', $reception['permissions']);
        $this->assertNotContains('housekeeping.update', $reception['permissions']);
        // Phase 6 post-build ruling: reception moves service-request status (not assign).
        $this->assertContains('service_requests.update', $reception['permissions']);
        $this->assertNotContains('service_requests.assign', $reception['permissions']);

        $housekeeping = collect($roles)->firstWhere('name', 'housekeeping');
        $this->assertNotNull($housekeeping);
        $this->assertContains('housekeeping.update', $housekeeping['permissions']);

        $contentEditor = collect($roles)->firstWhere('name', 'content_editor');
        $this->assertNotNull($contentEditor);
        $this->assertContains('cms.edit', $contentEditor['permissions']);
        $this->assertContains('cms.restore', $contentEditor['permissions']);
        $this->assertNotContains('cms.purge', $contentEditor['permissions']);

        $contentManager = collect($roles)->firstWhere('name', 'content_manager');
        $this->assertNotNull($contentManager);
        $this->assertContains('cms.purge', $contentManager['permissions']);
    }

    private function presetPermissions(string $role): array
    {
        return Role::findByName($role, 'users')->permissions->pluck('name')->sort()->values()->all();
    }

    /** Phase 7 D-10: reception and concierge gain tickets.*; no new permission strings. */
    public function test_reception_and_concierge_gain_ticket_permissions(): void
    {
        $this->assertSame([
            'folios.dispute', 'folios.post', 'folios.settle', 'folios.view',
            'guests.edit', 'guests.view', 'housekeeping.assign', 'housekeeping.view',
            'reservations.cancel', 'reservations.create', 'reservations.view', 'rooms.status',
            'service_requests.update', 'service_requests.view', 'tickets.respond', 'tickets.view',
        ], $this->presetPermissions('reception'));

        $this->assertSame([
            'guests.edit', 'guests.view', 'service_requests.assign', 'service_requests.update',
            'service_requests.view', 'tickets.assign', 'tickets.respond', 'tickets.view',
        ], $this->presetPermissions('concierge'));

        // Phase 8 (D-12): the events preset gains the events.* slice.
        $this->assertSame(
            ['events.deposit', 'events.manage', 'events.view', 'service_requests.view', 'tickets.assign', 'tickets.respond', 'tickets.view'],
            $this->presetPermissions('events'),
        );

        foreach (['reception', 'concierge'] as $role) {
            $this->assertEmpty(
                array_filter($this->presetPermissions($role), fn (string $p) => str_starts_with($p, 'events.')),
                "{$role} must not hold events.* (event inquiries moved off tickets.* in Phase 8)",
            );
        }

        foreach (['kitchen', 'housekeeping'] as $role) {
            $this->assertEmpty(
                array_filter($this->presetPermissions($role), fn (string $p) => str_starts_with($p, 'tickets.')),
                "{$role} must not hold tickets.* (it would open the guest chat inbox)",
            );
        }
    }

    private function presetToken(string $role): string
    {
        $this->app['auth']->forgetGuards();

        return User::factory()->create()->assignRole($role)->createToken('t')->plainTextToken;
    }

    /**
     * Council A4 / PR-6: tickets.* is the guest-relations set, so reception
     * and concierge also reach guest chat (read + reply). The event-inquiry
     * half of that blast radius moved to events.* in Phase 8 (D-12, PR-2):
     * both roles now get 403 on event inquiries and lose the summary key.
     * Pinned route by route; kitchen and housekeeping stay out.
     */
    public function test_ticket_preset_blast_radius(): void
    {
        $this->app->instance(FirebaseServiceInterface::class, new FakeFirebaseService());
        $conversation = Conversation::factory()->create();
        $inquiry      = EventInquiry::factory()->create();
        Ticket::factory()->create();

        foreach (['reception', 'concierge'] as $role) {
            $this->withToken($this->presetToken($role))->getJson('/api/cms/conversations')->assertOk();
            $this->withToken($this->presetToken($role))
                ->postJson("/api/cms/conversations/{$conversation->uuid}/messages", ['body' => "Hello from {$role}"])
                ->assertSuccessful();
            $this->withToken($this->presetToken($role))->getJson('/api/cms/event-inquiries')->assertStatus(403);

            $types = collect($this->withToken($this->presetToken($role))->getJson('/api/operations/queue')->assertOk()->json('data.items'))
                ->pluck('type');
            $this->assertContains('ticket', $types->all(), $role);

            $summary = $this->withToken($this->presetToken($role))->getJson('/api/dashboard/summary')->assertOk()->json('data');
            $this->assertArrayHasKey('tickets', $summary, $role);
            $this->assertArrayNotHasKey('event_inquiries', $summary, $role);
        }

        $concierge = User::factory()->create()->assignRole('concierge');
        $this->app['auth']->forgetGuards();
        $conciergeToken = $concierge->createToken('t')->plainTextToken;

        $this->withToken($conciergeToken)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/status", ['status' => 'in_review'])
            ->assertStatus(403);
        $this->withToken($conciergeToken)
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/assign", ['user_uuid' => $concierge->uuid])
            ->assertStatus(403);
        $this->assertSame('new', $inquiry->fresh()->status->value);

        $this->withToken($this->presetToken('reception'))
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/status", ['status' => 'quoted'])
            ->assertStatus(403);
        $this->withToken($this->presetToken('reception'))
            ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/assign", ['user_uuid' => $concierge->uuid])
            ->assertStatus(403);

        $ticket = Ticket::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->withToken($conciergeToken)
            ->patchJson("/api/support-tickets/{$ticket->uuid}/assign", ['user_uuid' => $concierge->uuid])
            ->assertOk();
        $this->withToken($this->presetToken('reception'))
            ->patchJson("/api/support-tickets/{$ticket->uuid}/assign", ['user_uuid' => $concierge->uuid])
            ->assertStatus(403);

        foreach (['kitchen', 'housekeeping'] as $role) {
            $this->withToken($this->presetToken($role))->getJson('/api/cms/conversations')->assertStatus(403);
            $this->withToken($this->presetToken($role))->getJson('/api/support-tickets')->assertStatus(403);
        }
    }
}
