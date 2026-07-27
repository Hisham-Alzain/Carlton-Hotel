<?php

namespace Tests\Feature\Service;

use App\Models\DiningVenue;
use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public reads that let the app discover the bookable_uuid values
 * POST /service-bookings requires.
 */
class PublicBookableTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_services_are_listed_without_a_token(): void
    {
        SpaService::factory()->count(2)->create(['is_active' => true]);
        SpaService::factory()->create(['is_active' => false]);

        $res = $this->getJson('/api/public/spa-services')->assertOk();

        $this->assertCount(2, $res->json('data.items'));
        $this->assertNotEmpty($res->json('data.items.0.uuid'));
        $this->assertNotEmpty($res->json('data.items.0.name.en'));
    }

    public function test_pool_cabanas_are_listed(): void
    {
        PoolCabana::factory()->count(2)->create(['is_active' => true]);
        PoolCabana::factory()->create(['is_active' => false]);

        $this->getJson('/api/public/pool-cabanas')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_transfers_are_listed(): void
    {
        Transfer::factory()->count(3)->create(['is_active' => true]);
        Transfer::factory()->create(['is_active' => false]);

        $this->getJson('/api/public/transfers')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }

    public function test_restaurant_tables_are_scoped_to_their_venue(): void
    {
        $venue = DiningVenue::factory()->create();
        $other = DiningVenue::factory()->create();

        RestaurantTable::create(['dining_venue_id' => $venue->id, 'table_number' => 'A-1', 'capacity' => 2, 'is_active' => true]);
        RestaurantTable::create(['dining_venue_id' => $venue->id, 'table_number' => 'A-2', 'capacity' => 4, 'is_active' => true]);
        RestaurantTable::create(['dining_venue_id' => $venue->id, 'table_number' => 'A-3', 'capacity' => 4, 'is_active' => false]);
        RestaurantTable::create(['dining_venue_id' => $other->id, 'table_number' => 'B-1', 'capacity' => 2, 'is_active' => true]);

        $res = $this->getJson("/api/public/dining-venues/{$venue->uuid}/tables")->assertOk();

        $this->assertCount(2, $res->json('data.items'));
        $this->assertSame('A-1', $res->json('data.items.0.table_number'));
        $this->assertSame(2, $res->json('data.items.0.capacity'));
    }

    public function test_a_listed_uuid_is_accepted_by_the_booking_endpoint(): void
    {
        $guest = \App\Models\Guest::factory()->create();
        \App\Models\Reservation::factory()->confirmed()->create([
            'guest_id'  => $guest->id,
            'check_in'  => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);
        SpaService::factory()->create(['is_active' => true]);

        $uuid = $this->getJson('/api/public/spa-services')->assertOk()->json('data.items.0.uuid');

        $this->actingAs($guest, 'guests')
            ->postJson('/api/service-bookings', [
                'bookable_type' => 'spa_service',
                'bookable_uuid' => $uuid,
                'scheduled_at'  => now()->addDay()->toDateTimeString(),
            ])
            ->assertCreated();
    }
}
