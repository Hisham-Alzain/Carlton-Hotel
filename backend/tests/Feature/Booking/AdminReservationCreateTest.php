<?php

namespace Tests\Feature\Booking;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/cms/reservations — reception booking on a guest's behalf.
 */
class AdminReservationCreateTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->roomType = RoomType::factory()->create(['base_price_usd' => 100, 'is_active' => true]);
        Room::factory()->create(['room_type_id' => $this->roomType->id, 'is_active' => true]);
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'     => 'Nour',
            'last_name'      => 'Haddad',
            'phone'          => '+963955123456',
            'room_type_uuid' => $this->roomType->uuid,
            'check_in'       => now()->addDays(3)->toDateString(),
            'check_out'      => now()->addDays(5)->toDateString(),
            'payment_method' => PaymentMethod::ON_ARRIVAL->value,
        ], $overrides);
    }

    public function test_reception_can_create_a_booking_for_a_new_guest(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ReservationStatus::CONFIRMED->value)
            ->assertJsonPath('data.source', ReservationSource::WALK_IN->value);

        $this->assertDatabaseHas('guests', ['phone' => '+963955123456', 'first_name' => 'Nour']);
        $this->assertSame(1, Reservation::count());
    }

    public function test_it_reserves_a_specific_room_and_prices_the_stay(): void
    {
        $response = $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload())
            ->assertCreated();

        // Two nights at 100/night, and a real room held from the moment of booking.
        $response->assertJsonPath('data.total_usd', '200.00');

        $reservation = Reservation::with('rooms')->first();
        $this->assertNotNull($reservation->rooms->first()->room_id);
    }

    public function test_an_existing_guest_is_reused_rather_than_duplicated(): void
    {
        $guest = Guest::factory()->create(['phone' => '+963955123456']);

        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload())
            ->assertCreated();

        $this->assertSame(1, Guest::where('phone', '+963955123456')->count());
        $this->assertSame($guest->id, Reservation::first()->guest_id);
    }

    public function test_it_accepts_a_guest_uuid_instead_of_contact_details(): void
    {
        $guest = Guest::factory()->create();

        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', [
                'guest_uuid'     => $guest->uuid,
                'room_type_uuid' => $this->roomType->uuid,
                'check_in'       => now()->addDays(3)->toDateString(),
                'check_out'      => now()->addDays(5)->toDateString(),
                'payment_method' => PaymentMethod::ON_ARRIVAL->value,
            ])
            ->assertCreated();

        $this->assertSame($guest->id, Reservation::first()->guest_id);
    }

    public function test_a_phone_booking_can_be_left_pending(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload([
                'status' => ReservationStatus::PENDING->value,
                'source' => ReservationSource::DIRECT->value,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', ReservationStatus::PENDING->value)
            ->assertJsonPath('data.source', ReservationSource::DIRECT->value);
    }

    public function test_the_guest_created_here_is_not_marked_verified(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload())
            ->assertCreated();

        $guest = Guest::where('phone', '+963955123456')->first();
        $this->assertNull($guest->phone_verified_at);
        $this->assertNull($guest->email_verified_at);
    }

    public function test_it_rejects_a_booking_with_no_way_to_identify_the_guest(): void
    {
        $payload = $this->payload();
        unset($payload['phone']);

        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('identity');
    }

    public function test_it_rejects_a_checkout_before_checkin(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload([
                'check_in'  => now()->addDays(5)->toDateString(),
                'check_out' => now()->addDays(3)->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_out');
    }

    public function test_it_rejects_an_unknown_room_type(): void
    {
        $this->withToken($this->staffToken('reservations.create'))
            ->postJson('/api/cms/reservations', $this->payload([
                'room_type_uuid' => '00000000-0000-0000-0000-000000000000',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('room_type_uuid');
    }

    public function test_it_reports_no_availability_when_every_room_is_taken(): void
    {
        $token = $this->staffToken('reservations.create');

        // The room type has exactly one room; the first booking consumes it.
        $this->withToken($token)->postJson('/api/cms/reservations', $this->payload())->assertCreated();

        $this->withToken($token)
            ->postJson('/api/cms/reservations', $this->payload(['phone' => '+963955999888']))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'no_availability');
    }

    public function test_staff_without_the_create_permission_are_refused(): void
    {
        $this->withToken($this->staffToken('reservations.view'))
            ->postJson('/api/cms/reservations', $this->payload())
            ->assertStatus(403);
    }

    public function test_an_unauthenticated_caller_is_refused(): void
    {
        $this->postJson('/api/cms/reservations', $this->payload())->assertStatus(401);
    }

    public function test_a_guest_token_cannot_reach_the_dashboard_endpoint(): void
    {
        $token = Guest::factory()->create()->createToken('g')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/cms/reservations', $this->payload())
            ->assertStatus(401);
    }
}
