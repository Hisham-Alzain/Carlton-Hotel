<?php

namespace Tests\Feature\Events;

use App\Events\InquirySubmitted;
use App\Models\EventInquiry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * @group p6
 */
class EventInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'       => 'Jane Doe',
            'email'      => 'jane@example.com',
            'event_type' => 'wedding',
        ], $overrides);
    }

    private function makeTriager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tickets.view', 'tickets.assign');
        return $user;
    }

    // ── Public submit ──────────────────────────────────────────────────────────

    public function test_anonymous_can_submit_inquiry(): void
    {
        $response = $this->postJson('/api/event-inquiries', $this->validPayload());

        $response->assertCreated()
                 ->assertJsonStructure(['data' => ['uuid', 'status', 'department']]);

        $this->assertDatabaseHas('event_inquiries', ['email' => 'jane@example.com', 'status' => 'new']);
    }

    public function test_corporate_event_routes_to_sales_department(): void
    {
        $this->postJson('/api/event-inquiries', $this->validPayload(['event_type' => 'corporate']))
             ->assertCreated()
             ->assertJsonPath('data.department', 'sales');
    }

    public function test_non_corporate_event_routes_to_events_department(): void
    {
        $this->postJson('/api/event-inquiries', $this->validPayload(['event_type' => 'wedding']))
             ->assertCreated()
             ->assertJsonPath('data.department', 'events');
    }

    public function test_inquiry_submitted_event_is_dispatched(): void
    {
        Event::fake([InquirySubmitted::class]);

        $this->postJson('/api/event-inquiries', $this->validPayload())->assertCreated();

        Event::assertDispatched(InquirySubmitted::class);
    }

    public function test_requirements_are_saved_with_inquiry(): void
    {
        $payload = $this->validPayload([
            'requirements' => [
                ['type' => 'catering', 'notes' => 'Halal menu'],
                ['type' => 'av'],
            ],
        ]);

        $this->postJson('/api/event-inquiries', $payload)->assertCreated();

        $this->assertDatabaseCount('event_requirements', 2);
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/event-inquiries', ['email' => 'jane@example.com'])
             ->assertStatus(422);
    }

    // ── Admin list / show ──────────────────────────────────────────────────────

    public function test_admin_can_list_inquiries(): void
    {
        EventInquiry::factory()->count(3)->create();
        $user = $this->makeTriager();

        $this->actingAs($user, 'users')
             ->getJson('/api/cms/event-inquiries')
             ->assertOk()
             ->assertJsonStructure(['data' => ['items', 'meta']]);
    }

    public function test_admin_can_show_inquiry(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $user    = $this->makeTriager();

        $this->actingAs($user, 'users')
             ->getJson("/api/cms/event-inquiries/{$inquiry->uuid}")
             ->assertOk()
             ->assertJsonPath('data.uuid', $inquiry->uuid);
    }

    public function test_permission_gate_blocks_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'users')
             ->getJson('/api/cms/event-inquiries')
             ->assertForbidden();
    }

    // ── Status transition ──────────────────────────────────────────────────────

    public function test_admin_can_update_status(): void
    {
        $inquiry = EventInquiry::factory()->create(['status' => 'new']);
        $user    = $this->makeTriager();

        $this->actingAs($user, 'users')
             ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/status", ['status' => 'in_review'])
             ->assertOk()
             ->assertJsonPath('data.status', 'in_review');
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $inquiry = EventInquiry::factory()->create(['status' => 'new']);
        $user    = $this->makeTriager();

        $this->actingAs($user, 'users')
             ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/status", ['status' => 'confirmed'])
             ->assertStatus(422);
    }

    // ── Assignment ─────────────────────────────────────────────────────────────

    public function test_admin_can_assign_inquiry_to_staff(): void
    {
        $inquiry = EventInquiry::factory()->create();
        $staff   = User::factory()->create();
        $user    = $this->makeTriager();

        $this->actingAs($user, 'users')
             ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/assign", ['user_uuid' => $staff->uuid])
             ->assertOk()
             ->assertJsonPath('data.assigned_to', $staff->uuid);
    }

    public function test_assign_to_new_inquiry_auto_moves_to_in_review(): void
    {
        $inquiry = EventInquiry::factory()->create(['status' => 'new']);
        $staff   = User::factory()->create();
        $user    = $this->makeTriager();

        $this->actingAs($user, 'users')
             ->patchJson("/api/cms/event-inquiries/{$inquiry->uuid}/assign", ['user_uuid' => $staff->uuid])
             ->assertOk()
             ->assertJsonPath('data.status', 'in_review');
    }
}
