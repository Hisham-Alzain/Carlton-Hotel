<?php

namespace Tests\Feature\Auth;

use App\Models\Guest;
use App\Models\Reservation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group p6_5
 */
class GuestMeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingGuest(Guest $guest)
    {
        return $this->actingAs($guest, 'guests');
    }

    public function test_me_with_no_reservation_returns_both_flags_false(): void
    {
        $guest = Guest::factory()->create();

        $this->actingGuest($guest)
             ->getJson('/api/auth/guest/me')
             ->assertOk()
             ->assertJson(['data' => [
                 'has_booking'            => false,
                 'is_checked_in'          => false,
                 'has_active_reservation' => false,
             ]])
             ->assertJsonPath('data.active_reservation', null);
    }

    public function test_me_with_confirmed_reservation_unlocks_pre_arrival_only(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create(['guest_id' => $guest->id]);

        $this->actingGuest($guest)
             ->getJson('/api/auth/guest/me')
             ->assertOk()
             ->assertJson(['data' => [
                 'has_booking'            => true,
                 'is_checked_in'          => false,
                 'has_active_reservation' => true,
             ]]);
    }

    public function test_me_with_checked_in_reservation_unlocks_both_tiers(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->checkedIn()->create(['guest_id' => $guest->id]);

        $this->actingGuest($guest)
             ->getJson('/api/auth/guest/me')
             ->assertOk()
             ->assertJson(['data' => [
                 'has_booking'            => true,
                 'is_checked_in'          => true,
                 'has_active_reservation' => true,
             ]]);
    }

    public function test_has_active_reservation_alias_matches_has_booking(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create(['guest_id' => $guest->id]);

        $response = $this->actingGuest($guest)->getJson('/api/auth/guest/me')->assertOk();

        $data = $response->json('data');
        $this->assertEquals($data['has_booking'], $data['has_active_reservation']);
    }

    public function test_pending_reservation_does_not_unlock_pre_arrival(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->create(['guest_id' => $guest->id, 'status' => Reservation::STATUS_PENDING]);

        $this->actingGuest($guest)
             ->getJson('/api/auth/guest/me')
             ->assertOk()
             ->assertJson(['data' => ['has_booking' => false, 'is_checked_in' => false]]);
    }

    public function test_confirmed_reservation_with_past_checkout_does_not_unlock_pre_arrival(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->confirmed()->create([
            'guest_id'  => $guest->id,
            'check_in'  => now()->subDays(10)->toDateString(),
            'check_out' => now()->subDays(5)->toDateString(),
        ]);

        $this->actingGuest($guest)
             ->getJson('/api/auth/guest/me')
             ->assertOk()
             ->assertJson(['data' => ['has_booking' => false, 'is_checked_in' => false]]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/auth/guest/me')->assertUnauthorized();
    }
}
