<?php

namespace Tests\Feature\Service;

use App\Models\Guest;
use App\Models\PoolCabana;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p7
 */
class ServiceBookingTest extends TestCase
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

    public function test_books_spa_service(): void
    {
        $guest = $this->checkedInGuest();
        $spa   = SpaService::factory()->create();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'spa_service',
                 'bookable_uuid' => $spa->uuid,
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertCreated()
             ->assertJsonPath('data.bookable_type', 'spa_service')
             ->assertJsonPath('data.bookable.uuid', $spa->uuid);

        $this->assertDatabaseHas('service_bookings', ['bookable_type' => 'spa_service', 'guest_id' => $guest->id]);
    }

    public function test_books_restaurant_table(): void
    {
        $guest = $this->checkedInGuest();
        $table = RestaurantTable::factory()->create();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'restaurant_table',
                 'bookable_uuid' => $table->uuid,
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertCreated()
             ->assertJsonPath('data.bookable_type', 'restaurant_table');
    }

    public function test_books_pool_cabana(): void
    {
        $guest  = $this->checkedInGuest();
        $cabana = PoolCabana::factory()->create();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'pool_cabana',
                 'bookable_uuid' => $cabana->uuid,
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertCreated()
             ->assertJsonPath('data.bookable_type', 'pool_cabana');
    }

    public function test_books_transfer(): void
    {
        $guest    = $this->checkedInGuest();
        $transfer = Transfer::factory()->create();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'transfer',
                 'bookable_uuid' => $transfer->uuid,
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertCreated()
             ->assertJsonPath('data.bookable_type', 'transfer');
    }

    public function test_unknown_bookable_uuid_returns_404(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'spa_service',
                 'bookable_uuid' => (string) \Illuminate\Support\Str::uuid(),
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertStatus(404);
    }

    public function test_invalid_bookable_type_returns_422(): void
    {
        $guest = $this->checkedInGuest();

        $this->actingAs($guest, 'guests')
             ->postJson('/api/service-bookings', [
                 'bookable_type' => 'not_a_type',
                 'bookable_uuid' => 'anything',
                 'scheduled_at'  => now()->addDay()->toDateTimeString(),
             ])
             ->assertStatus(422);
    }
}
