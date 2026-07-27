<?php

namespace Tests\Feature\Service;

use App\Enums\ServiceBookingStatus;
use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function bookedGuest(): Guest
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create([
            'guest_id'  => $guest->id,
            'check_in'  => now()->subDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
        ]);
        return $guest;
    }

    private function venueWithTables(array $capacities = [2, 4, 6]): DiningVenue
    {
        $venue = DiningVenue::factory()->create();
        foreach ($capacities as $i => $capacity) {
            RestaurantTable::create([
                'dining_venue_id' => $venue->id,
                'table_number'    => "T-{$i}",
                'capacity'        => $capacity,
                'is_active'       => true,
            ]);
        }
        return $venue;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date'            => now()->addDay()->toDateString(),
            'time'            => '19:30',
            'guest_count'     => 2,
            'special_request' => 'Window seat please',
        ], $overrides);
    }

    // ── Happy path ────────────────────────────────────────────────────────

    public function test_guest_can_reserve_a_table(): void
    {
        $guest = $this->bookedGuest();
        $venue = $this->venueWithTables();

        $this->actingAs($guest, 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.bookable_type', 'restaurant_table')
            ->assertJsonPath('data.guest_count', 2)
            ->assertJsonPath('data.notes', 'Window seat please')
            ->assertJsonPath('data.status', ServiceBookingStatus::PENDING->value);
    }

    public function test_smallest_fitting_table_is_assigned(): void
    {
        $guest = $this->bookedGuest();
        $venue = $this->venueWithTables([2, 4, 6]);

        $this->actingAs($guest, 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload(['guest_count' => 3]))
            ->assertCreated();

        $booking = ServiceBooking::latest('id')->first();
        $this->assertSame(4, RestaurantTable::find($booking->bookable_id)->capacity);
    }

    public function test_party_larger_than_every_table_is_rejected(): void
    {
        $guest = $this->bookedGuest();
        $venue = $this->venueWithTables([2, 4]);

        $this->actingAs($guest, 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload(['guest_count' => 8]))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'no_availability');
    }

    // ── Seating window ────────────────────────────────────────────────────

    public function test_a_taken_table_is_not_double_booked_in_the_same_window(): void
    {
        $venue = $this->venueWithTables([2]);
        $slot  = ['date' => now()->addDay()->toDateString(), 'time' => '19:30'];

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload($slot))
            ->assertCreated();

        // Only one table exists and it is held for a two-hour seating.
        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload($slot))
            ->assertStatus(409);
    }

    public function test_the_same_table_is_reusable_outside_the_seating_window(): void
    {
        $venue = $this->venueWithTables([2]);
        $date  = now()->addDay()->toDateString();

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload(['date' => $date, 'time' => '12:00']))
            ->assertCreated();

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload(['date' => $date, 'time' => '20:00']))
            ->assertCreated();
    }

    public function test_a_cancelled_seating_frees_the_table(): void
    {
        $venue = $this->venueWithTables([2]);
        $slot  = ['date' => now()->addDay()->toDateString(), 'time' => '19:30'];

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload($slot))
            ->assertCreated();

        ServiceBooking::latest('id')->first()->update(['status' => ServiceBookingStatus::CANCELLED]);

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload($slot))
            ->assertCreated();
    }

    // ── Auth + validation ─────────────────────────────────────────────────

    public function test_unauthenticated_cannot_reserve(): void
    {
        $venue = $this->venueWithTables();
        $this->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload())
            ->assertStatus(401);
    }

    public function test_guest_without_a_booking_cannot_reserve(): void
    {
        $venue = $this->venueWithTables();

        $this->actingAs(Guest::factory()->create(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload())
            ->assertStatus(403);
    }

    public function test_past_dates_are_rejected(): void
    {
        $venue = $this->venueWithTables();

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload([
                'date' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    public function test_guest_count_is_required(): void
    {
        $venue   = $this->venueWithTables();
        $payload = $this->payload();
        unset($payload['guest_count']);

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $payload)
            ->assertStatus(422);
    }

    public function test_inactive_venue_returns_404(): void
    {
        $venue = $this->venueWithTables();
        $venue->update(['is_active' => false]);

        $this->actingAs($this->bookedGuest(), 'guests')
            ->postJson("/api/dining-venues/{$venue->uuid}/table-reservations", $this->payload())
            ->assertNotFound();
    }
}
