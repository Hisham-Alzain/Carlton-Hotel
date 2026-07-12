<?php

namespace Tests\Feature\Service;

use App\Models\Guest;
use App\Models\Reservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p7
 */
class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function checkedInGuest(): Guest
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id]);
        return $guest;
    }

    public function test_room_service_request_routes_to_kitchen_department(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', ['type' => 'room_service'])
             ->assertCreated()
             ->assertJsonPath('data.department', 'kitchen')
             ->assertJsonPath('data.status', 'new');
    }

    public function test_housekeeping_request_routes_to_housekeeping_department(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', ['type' => 'housekeeping'])
             ->assertCreated()
             ->assertJsonPath('data.department', 'housekeeping');
    }

    public function test_unmapped_type_defaults_to_concierge_department(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', ['type' => 'wake_up_call'])
             ->assertCreated()
             ->assertJsonPath('data.department', 'concierge');
    }

    public function test_guest_can_list_own_requests(): void
    {
        $guest = $this->checkedInGuest();
        $this->actingAs($guest, 'guests')->postJson('/api/service-requests', ['type' => 'room_service'])->assertCreated();
        $this->actingAs($guest, 'guests')->postJson('/api/service-requests', ['type' => 'housekeeping'])->assertCreated();

        $this->actingAs($guest, 'guests')
             ->getJson('/api/service-requests')
             ->assertOk()
             ->assertJsonCount(2, 'data.items');
    }

    public function test_validation_requires_type(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', [])
             ->assertStatus(422);
    }
}
