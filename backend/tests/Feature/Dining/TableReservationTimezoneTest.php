<?php

namespace Tests\Feature\Dining;

use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DINING-01 / D-22 regression: the guest's date + time are hotel-local and are
 * stored as the true UTC instant of that slot (Asia/Damascus = UTC+3, no DST).
 */
class TableReservationTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function bookedGuest(): Guest
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create([
            'guest_id'  => $guest->id,
            'check_in'  => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
        ]);

        return $guest;
    }

    private function venue(): DiningVenue
    {
        $venue = DiningVenue::factory()->create();
        RestaurantTable::create(['dining_venue_id' => $venue->id, 'table_number' => 'T-1', 'capacity' => 4, 'is_active' => true]);

        return $venue;
    }

    private function book(Guest $guest, DiningVenue $venue, string $date, string $time)
    {
        return $this->actingAs($guest, 'guests')->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", [
            'date' => $date, 'time' => $time, 'guest_count' => 2,
        ]);
    }

    public function test_local_slot_is_stored_as_true_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $guest = $this->bookedGuest();
        $venue = $this->venue();

        $response = $this->book($guest, $venue, '2026-11-12', '19:00')->assertCreated();

        $this->assertSame('2026-11-12 16:00:00', ServiceBooking::sole()->getRawOriginal('scheduled_at'));
        $this->assertTrue(
            CarbonImmutable::parse($response->json('data.scheduled_at'))->utc()->equalTo(CarbonImmutable::parse('2026-11-12 16:00:00', 'UTC')),
        );
    }

    public function test_today_is_the_hotel_day(): void
    {
        // 22:30Z on the 10th is 01:30 on the 11th in Damascus.
        $this->travelTo(CarbonImmutable::parse('2026-11-10 22:30:00', 'UTC'));
        $guest = $this->bookedGuest();
        $venue = $this->venue();

        $this->book($guest, $venue, '2026-11-10', '20:00')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['date']);

        $this->book($guest, $venue, '2026-11-11', '20:00')->assertCreated();
    }

    public function test_slot_after_local_midnight_keeps_its_local_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00', 'UTC'));
        $guest = $this->bookedGuest();
        $venue = $this->venue();

        $this->book($guest, $venue, '2026-11-12', '01:00')->assertCreated();

        $this->assertSame('2026-11-11 22:00:00', ServiceBooking::sole()->getRawOriginal('scheduled_at'));
    }
}
