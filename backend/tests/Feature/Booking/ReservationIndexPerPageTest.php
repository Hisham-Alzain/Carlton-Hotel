<?php

namespace Tests\Feature\Booking;

use App\Models\Guest;
use App\Models\Reservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IT6-02: GET /api/reservations honours per_page (default 15, ceiling 100,
 * a missing / zero / non-numeric value falls back to 15 and is never an error).
 */
class ReservationIndexPerPageTest extends TestCase
{
    use RefreshDatabase;

    private Guest $guest;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->guest = Guest::factory()->create();
        $this->token = $this->guest->createToken('t')->plainTextToken;
    }

    private function seedReservations(int $count): void
    {
        Reservation::factory()->count($count)->create(['guest_id' => $this->guest->id]);
    }

    public function test_per_page_is_honoured(): void
    {
        $this->seedReservations(7);
        Reservation::factory()->create(['guest_id' => Guest::factory()->create()->id]);

        $this->withToken($this->token)->getJson('/api/reservations?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total', 7)
            ->assertJsonPath('data.meta.last_page', 2);
    }

    public function test_per_page_is_clamped_to_one_hundred(): void
    {
        $this->seedReservations(3);

        $this->withToken($this->token)->getJson('/api/reservations?per_page=500')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 100);
    }

    public function test_a_missing_zero_or_non_numeric_per_page_falls_back_to_fifteen(): void
    {
        $this->seedReservations(3);

        foreach (['', '?per_page=0', '?per_page=abc', '?per_page=-4'] as $query) {
            $this->withToken($this->token)->getJson('/api/reservations'.$query)
                ->assertOk()
                ->assertJsonPath('data.meta.per_page', 15);
        }
    }

    public function test_another_guests_reservations_never_appear(): void
    {
        $this->seedReservations(2);
        Reservation::factory()->count(3)->create(['guest_id' => Guest::factory()->create()->id]);

        $this->withToken($this->token)->getJson('/api/reservations?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_an_unauthenticated_list_is_rejected(): void
    {
        $this->getJson('/api/reservations?per_page=5')->assertStatus(401);
    }
}
