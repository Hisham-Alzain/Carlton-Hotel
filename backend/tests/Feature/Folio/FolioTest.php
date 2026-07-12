<?php

namespace Tests\Feature\Folio;

use App\Models\Guest;
use App\Models\PoolCabana;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p8
 */
class FolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function checkedInGuest(): array
    {
        $guest       = Guest::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id, 'total_usd' => 300]);
        return [$guest, $reservation];
    }

    private function makeStaff(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user;
    }

    public function test_folio_aggregates_room_charge_and_priced_service_bookings(): void
    {
        [$guest, $reservation] = $this->checkedInGuest();

        $cabana = PoolCabana::factory()->create(['price_usd' => 80]);
        ServiceBooking::factory()->create([
            'guest_id'       => $guest->id,
            'reservation_id' => $reservation->id,
            'bookable_type'  => 'pool_cabana',
            'bookable_id'    => $cabana->id,
            'status'         => ServiceBooking::STATUS_CONFIRMED,
        ]);

        // Restaurant table booking carries no charge — must be excluded
        $table = RestaurantTable::factory()->create();
        ServiceBooking::factory()->create([
            'guest_id'       => $guest->id,
            'reservation_id' => $reservation->id,
            'bookable_type'  => 'restaurant_table',
            'bookable_id'    => $table->id,
            'status'         => ServiceBooking::STATUS_CONFIRMED,
        ]);

        $staff = $this->makeStaff(['folios.view']);

        $response = $this->actingAs($staff, 'users')
            ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
            ->assertOk();

        $response->assertJsonPath('data.total_usd', '380.00')
                  ->assertJsonCount(2, 'data.items');
    }

    public function test_cancelled_service_booking_is_not_charged(): void
    {
        [$guest, $reservation] = $this->checkedInGuest();
        $cabana = PoolCabana::factory()->create(['price_usd' => 80]);
        ServiceBooking::factory()->create([
            'guest_id'       => $guest->id,
            'reservation_id' => $reservation->id,
            'bookable_type'  => 'pool_cabana',
            'bookable_id'    => $cabana->id,
            'status'         => ServiceBooking::STATUS_CANCELLED,
        ]);

        $staff = $this->makeStaff(['folios.view']);

        $this->actingAs($staff, 'users')
             ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
             ->assertJsonPath('data.total_usd', '300.00')
             ->assertJsonCount(1, 'data.items');
    }

    public function test_guest_can_view_own_folio(): void
    {
        [$guest, ] = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->getJson('/api/folio')
             ->assertOk()
             ->assertJsonPath('data.status', 'open');
    }

    public function test_admin_settle_creates_payment_and_closes_folio(): void
    {
        [$guest, $reservation] = $this->checkedInGuest();
        $viewer  = $this->makeStaff(['folios.view']);
        $settler = $this->makeStaff(['folios.settle']);

        $folioUuid = $this->actingAs($viewer, 'users')
            ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
            ->json('data.uuid');

        $this->actingAs($settler, 'users')
             ->postJson("/api/cms/folios/{$folioUuid}/settle", [
                 'method'     => 'cash',
                 'amount_usd' => 300,
             ])
             ->assertOk()
             ->assertJsonPath('data.status', 'settled');

        $this->assertDatabaseHas('payments', ['payable_type' => 'App\\Models\\Folio', 'method' => 'cash']);
    }

    public function test_settled_folio_does_not_drift_on_regeneration(): void
    {
        [$guest, $reservation] = $this->checkedInGuest();
        $viewer  = $this->makeStaff(['folios.view']);
        $settler = $this->makeStaff(['folios.settle']);

        $this->actingAs($viewer, 'users')->postJson("/api/cms/folios/{$reservation->uuid}/generate")->assertOk();

        $folio = \App\Models\Folio::where('reservation_id', $reservation->id)->firstOrFail();

        $this->actingAs($settler, 'users')
             ->postJson("/api/cms/folios/{$folio->uuid}/settle", ['method' => 'cash', 'amount_usd' => 300])
             ->assertOk();

        // A new priced booking confirmed after settlement must not silently change the settled total.
        $cabana = PoolCabana::factory()->create(['price_usd' => 80]);
        ServiceBooking::factory()->create([
            'guest_id'       => $guest->id,
            'reservation_id' => $reservation->id,
            'bookable_type'  => 'pool_cabana',
            'bookable_id'    => $cabana->id,
            'status'         => ServiceBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($viewer, 'users')
             ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
             ->assertOk()
             ->assertJsonPath('data.status', 'settled')
             ->assertJsonPath('data.total_usd', '300.00');
    }

    public function test_settling_already_settled_folio_returns_422(): void
    {
        [$guest, $reservation] = $this->checkedInGuest();
        $viewer  = $this->makeStaff(['folios.view']);
        $settler = $this->makeStaff(['folios.settle']);

        $folioUuid = $this->actingAs($viewer, 'users')
            ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
            ->json('data.uuid');

        $this->actingAs($settler, 'users')
             ->postJson("/api/cms/folios/{$folioUuid}/settle", ['method' => 'cash', 'amount_usd' => 300])
             ->assertOk();

        $this->actingAs($settler, 'users')
             ->postJson("/api/cms/folios/{$folioUuid}/settle", ['method' => 'cash', 'amount_usd' => 300])
             ->assertStatus(422)
             ->assertJson(['error_code' => 'reservation_state']);
    }

    public function test_guest_approve_marks_reservation_checked_out(): void
    {
        [$guest, $reservation] = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/folio/approve')
             ->assertOk()
             ->assertJsonStructure(['data' => ['approved_by_guest_at']]);

        $this->assertEquals(Reservation::STATUS_CHECKED_OUT, $reservation->fresh()->status);
    }

    public function test_transport_request_creates_service_request(): void
    {
        [$guest, ] = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/transport-requests', ['notes' => 'Airport at 6am'])
             ->assertCreated()
             ->assertJsonPath('data.type', 'transport')
             ->assertJsonPath('data.department', 'concierge');

        $this->assertDatabaseHas('service_requests', ['type' => 'transport', 'guest_id' => $guest->id]);
    }

    public function test_permission_gate_blocks_generate_without_folios_view(): void
    {
        [, $reservation] = $this->checkedInGuest();
        $user = User::factory()->create();

        $this->actingAs($user, 'users')
             ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
             ->assertForbidden();
    }

    public function test_permission_gate_blocks_settle_without_folios_settle(): void
    {
        [, $reservation] = $this->checkedInGuest();
        $viewer = $this->makeStaff(['folios.view']);

        $folioUuid = $this->actingAs($viewer, 'users')
            ->postJson("/api/cms/folios/{$reservation->uuid}/generate")
            ->json('data.uuid');

        $this->actingAs($viewer, 'users')
             ->postJson("/api/cms/folios/{$folioUuid}/settle", ['method' => 'cash', 'amount_usd' => 300])
             ->assertForbidden();
    }

    public function test_booked_not_checked_in_guest_cannot_access_folio(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create(['guest_id' => $guest->id]);

        $this->actingAs($guest, 'guests')
             ->getJson('/api/folio')
             ->assertStatus(403)
             ->assertJson(['error_code' => 'no_active_reservation']);
    }
}
