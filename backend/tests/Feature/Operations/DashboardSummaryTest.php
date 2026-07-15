<?php

namespace Tests\Feature\Operations;

use App\Models\EventInquiry;
use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p10
 */
class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    public function test_summary_only_includes_blocks_the_caller_can_view(): void
    {
        ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);
        Ticket::factory()->create(['status' => Ticket::STATUS_OPEN]);
        EventInquiry::factory()->create(['status' => 'new']);

        $response = $this->withToken($this->staffToken('service_requests.view'))
            ->getJson('/api/dashboard/summary')
            ->assertOk();

        $response->assertJsonPath('data.service_requests.new', 1)
                  ->assertJsonMissingPath('data.tickets')
                  ->assertJsonMissingPath('data.event_inquiries');
    }

    public function test_tickets_view_unlocks_both_tickets_and_event_inquiry_blocks(): void
    {
        Ticket::factory()->create(['status' => Ticket::STATUS_OPEN]);
        EventInquiry::factory()->create(['status' => 'new']);

        $response = $this->withToken($this->staffToken('tickets.view'))
            ->getJson('/api/dashboard/summary')
            ->assertOk();

        $response->assertJsonPath('data.tickets.open', 1)
                  ->assertJsonPath('data.event_inquiries.new', 1)
                  ->assertJsonMissingPath('data.service_requests');
    }

    public function test_no_permissions_returns_an_empty_summary(): void
    {
        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_unauthenticated_staff_request_returns_401(): void
    {
        $this->getJson('/api/dashboard/summary')->assertStatus(401);
    }
}
