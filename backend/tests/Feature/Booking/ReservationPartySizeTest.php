<?php

namespace Tests\Feature\Booking;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsLoyaltyFixtures;
use Tests\TestCase;

/**
 * IT6-01: optional party size (`adults` default 1, `children` default 0) on
 * POST /api/reservations and POST /api/cms/reservations, capped by the room
 * type's max_occupancy (`occupancy_exceeded`, 422).
 */
class ReservationPartySizeTest extends TestCase
{
    use BuildsLoyaltyFixtures;
    use RefreshDatabase;

    private RoomType $roomType;

    private Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->roomType = RoomType::factory()->create([
            'base_price_usd' => 100,
            'base_occupancy' => 2,
            'max_occupancy' => 3,
            'is_active' => true,
        ]);
        Room::factory()->count(3)->create(['room_type_id' => $this->roomType->id, 'is_active' => true]);
        $this->guest = Guest::factory()->create();
    }

    /** @return array<string, mixed> */
    private function body(array $extra = []): array
    {
        return array_merge([
            'room_type_uuid' => $this->roomType->uuid,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'payment_method' => 'on_arrival',
        ], $extra);
    }

    private function book(array $extra = [], array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->guestToken($this->guest))
            ->withHeaders($headers)
            ->postJson('/api/reservations', $this->body($extra));
    }

    /** @return array<string, mixed> */
    private function staffBody(array $extra = []): array
    {
        return array_merge($this->body(), [
            'first_name' => 'Nour',
            'last_name' => 'Haddad',
            'phone' => '+963955123456',
        ], $extra);
    }

    private function staffBook(array $extra = [], string ...$permissions): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->staffToken(...$permissions))
            ->postJson('/api/cms/reservations', $this->staffBody($extra));
    }

    public function test_omitted_party_size_defaults_to_one_adult_no_children(): void
    {
        $res = $this->book()->assertCreated()
            ->assertJsonPath('data.adults', 1)
            ->assertJsonPath('data.children', 0);

        $this->assertDatabaseHas('reservations', ['uuid' => $res->json('data.uuid'), 'adults' => 1, 'children' => 0]);
    }

    public function test_null_party_size_means_the_default(): void
    {
        $this->book(['adults' => null, 'children' => null])->assertCreated()
            ->assertJsonPath('data.adults', 1)
            ->assertJsonPath('data.children', 0);
    }

    public function test_party_size_is_stored_and_returned_by_the_guest_list_and_detail(): void
    {
        $uuid = $this->book(['adults' => 2, 'children' => 1])->assertCreated()
            ->assertJsonPath('data.adults', 2)
            ->assertJsonPath('data.children', 1)
            ->json('data.uuid');

        $this->assertDatabaseHas('reservations', ['uuid' => $uuid, 'adults' => 2, 'children' => 1]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken($this->guest))->getJson("/api/reservations/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.adults', 2)
            ->assertJsonPath('data.children', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->guestToken($this->guest))->getJson('/api/reservations')
            ->assertOk()
            ->assertJsonPath('data.items.0.adults', 2)
            ->assertJsonPath('data.items.0.children', 1);
    }

    public function test_a_party_exactly_at_max_occupancy_is_accepted(): void
    {
        $this->book(['adults' => 2, 'children' => 1])->assertCreated();
    }

    public function test_a_party_over_max_occupancy_is_refused_and_writes_nothing(): void
    {
        $this->book(['adults' => 3, 'children' => 1])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'occupancy_exceeded')
            ->assertJsonPath('context.max_occupancy', 3)
            ->assertJsonPath('context.requested', 4);

        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, DB::table('reservation_rooms')->count());
    }

    public function test_the_occupancy_message_is_localised(): void
    {
        $this->book(['adults' => 4], ['Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This room type sleeps at most 3 guests.');
    }

    public function test_invalid_party_size_values_fail_validation_on_the_field(): void
    {
        $this->book(['adults' => 0])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['adults'], 'errors');

        $this->book(['children' => -1])->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['children'], 'errors');

        $this->book(['adults' => 'abc'])->assertStatus(422)
            ->assertJsonValidationErrors(['adults'], 'errors');

        $this->book(['adults' => 21])->assertStatus(422)
            ->assertJsonValidationErrors(['adults'], 'errors');

        $this->assertSame(0, Reservation::count());
    }

    public function test_an_unauthenticated_booking_is_rejected(): void
    {
        $this->postJson('/api/reservations', $this->body(['adults' => 2]))->assertStatus(401);
    }

    public function test_a_loyalty_replay_with_the_same_party_is_answered_and_a_different_party_conflicts(): void
    {
        $this->configureLoyalty([
            'redeem_value_usd' => '0.0100',
            'max_redeem_percent' => '50.00',
            'min_redeem_points' => 100,
        ]);
        $this->grantPoints($this->guest, 20000);

        $first = $this->book(['adults' => 2, 'loyalty_points' => 1000], ['Idempotency-Key' => 'PK-1'])
            ->assertCreated();

        $this->book(['adults' => 1, 'loyalty_points' => 1000], ['Idempotency-Key' => 'PK-1'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict');

        $this->book(['adults' => 2, 'children' => 1, 'loyalty_points' => 1000], ['Idempotency-Key' => 'PK-1'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'idempotency_conflict');

        $this->book(['adults' => 2, 'loyalty_points' => 1000], ['Idempotency-Key' => 'PK-1'])
            ->assertOk()
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));

        $this->assertSame(1, Reservation::count());
    }

    // ---- staff: POST /api/cms/reservations ----

    public function test_staff_can_record_party_size(): void
    {
        $uuid = $this->staffBook(['adults' => 2, 'children' => 1], 'reservations.create')
            ->assertCreated()
            ->assertJsonPath('data.adults', 2)
            ->assertJsonPath('data.children', 1)
            ->json('data.uuid');

        $this->assertDatabaseHas('reservations', ['uuid' => $uuid, 'adults' => 2, 'children' => 1]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->staffToken('reservations.view'))->getJson("/api/cms/reservations/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.adults', 2)
            ->assertJsonPath('data.children', 1);
    }

    public function test_staff_omitting_party_size_gets_the_default(): void
    {
        $this->staffBook([], 'reservations.create')->assertCreated()
            ->assertJsonPath('data.adults', 1)
            ->assertJsonPath('data.children', 0);
    }

    public function test_staff_party_over_max_occupancy_is_refused(): void
    {
        $this->staffBook(['adults' => 2, 'children' => 2], 'reservations.create')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'occupancy_exceeded')
            ->assertJsonPath('context.max_occupancy', 3)
            ->assertJsonPath('context.requested', 4);

        $this->assertSame(0, Reservation::count());
    }

    public function test_staff_invalid_party_size_fails_validation(): void
    {
        $this->staffBook(['adults' => 0], 'reservations.create')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['adults'], 'errors');
    }

    public function test_staff_without_the_permission_is_forbidden(): void
    {
        $this->staffBook(['adults' => 2], 'reservations.view')->assertStatus(403);
        $this->assertSame(0, Reservation::count());
    }

    public function test_staff_booking_without_a_token_is_unauthorised(): void
    {
        $this->postJson('/api/cms/reservations', $this->staffBody(['adults' => 2]))->assertStatus(401);
    }
}
