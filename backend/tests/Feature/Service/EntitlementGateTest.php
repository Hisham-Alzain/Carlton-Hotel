<?php

namespace Tests\Feature\Service;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\SpaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @group p7
 */
class EntitlementGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    private function preArrivalPayload(): array
    {
        return [
            'documents' => [
                ['type' => 'passport', 'file' => \Illuminate\Http\UploadedFile::fake()->create('passport.pdf', 100)],
            ],
        ];
    }

    private function inRoomPayload(): array
    {
        return ['type' => 'room_service', 'notes' => 'Two coffees'];
    }

    public function test_guest_with_no_booking_is_rejected_on_pre_arrival_and_in_room(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', $this->preArrivalPayload())
             ->assertStatus(403)
             ->assertJson(['error_code' => 'no_active_reservation']);

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', $this->inRoomPayload())
             ->assertStatus(403)
             ->assertJson(['error_code' => 'no_active_reservation']);
    }

    public function test_booked_not_checked_in_guest_allowed_pre_arrival_rejected_in_room(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create(['guest_id' => $guest->id]);

        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', $this->preArrivalPayload())
             ->assertCreated();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', $this->inRoomPayload())
             ->assertStatus(403)
             ->assertJson(['error_code' => 'no_active_reservation']);
    }

    public function test_checked_in_guest_allowed_both_tiers(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id]);
        $spa = SpaService::factory()->create();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/pre-arrival/documents', $this->preArrivalPayload())
             ->assertCreated();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-requests', $this->inRoomPayload())
             ->assertCreated();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'spa_service',
                 'bookable_uuid' => $spa->uuid,
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertCreated();
    }

    public function test_unauthenticated_guest_is_rejected(): void
    {
        $this->postJson('/api/service-requests', $this->inRoomPayload())->assertUnauthorized();
        $this->postJson('/api/pre-arrival/documents', $this->preArrivalPayload())->assertUnauthorized();
    }
}
