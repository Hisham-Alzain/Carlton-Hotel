<?php

namespace Tests\Feature\Dining;

use App\Enums\BookableType;
use App\Enums\ServiceBookingStatus;
use App\Http\Resources\Dining\TableReservationResource;
use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Services\Dining\TableReservationService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * DINING-01 (D-10, D-13, D-23..D-25, D-30, PR-3, PR-4): the restaurant's
 * table-reservation list, in hotel-local days (Asia/Damascus = UTC+3).
 */
class TableReservationIndexTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private DiningVenue $venue;
    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->token = User::factory()->create()->assignRole('kitchen')->createToken('t')->plainTextToken;
        $this->venue = DiningVenue::factory()->create(['name' => ['en' => 'Rooftop Grill', 'ar' => 'مشاوي السطح']]);
        $this->table = RestaurantTable::factory()->create(['dining_venue_id' => $this->venue->id, 'table_number' => 'T-7', 'capacity' => 4]);
    }

    private function booking(string $utc, array $attributes = [], ?RestaurantTable $table = null): ServiceBooking
    {
        $guest = $attributes['guest'] ?? Guest::factory()->create();
        unset($attributes['guest']);

        return ServiceBooking::factory()->create(array_merge([
            'guest_id'       => $guest->id,
            'reservation_id' => Reservation::factory()->create(['guest_id' => $guest->id])->id,
            'bookable_type'  => BookableType::RESTAURANT_TABLE->value,
            'bookable_id'    => ($table ?? $this->table)->id,
            'scheduled_at'   => CarbonImmutable::parse($utc, 'UTC'),
            'guest_count'    => 2,
            'status'         => ServiceBookingStatus::CONFIRMED,
        ], $attributes));
    }

    private function list(string $query = ''): TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/cms/table-reservations' . ($query === '' ? '' : "?{$query}"));
    }

    private function uuids(TestResponse $response): array
    {
        return array_column($response->assertOk()->json('data.items'), 'uuid');
    }

    public function test_defaults_to_the_hotel_local_today(): void
    {
        $early  = $this->booking('2026-11-09 21:30:00'); // 00:30 local on the 10th
        $late   = $this->booking('2026-11-10 20:59:00'); // 23:59 local on the 10th
        $this->booking('2026-11-10 21:00:00');            // 00:00 local on the 11th
        $this->booking('2026-11-09 20:00:00');            // 23:00 local on the 9th

        $this->assertSame([$early->uuid, $late->uuid], $this->uuids($this->list()));
    }

    public function test_date_filter(): void
    {
        $this->booking('2026-11-10 20:59:00');
        $next = $this->booking('2026-11-10 21:00:00');

        $this->assertSame([$next->uuid], $this->uuids($this->list('date=2026-11-11')));
    }

    public function test_range_filter_spans_both_days(): void
    {
        $a = $this->booking('2026-11-10 08:00:00');
        $b = $this->booking('2026-11-11 08:00:00');
        $this->booking('2026-11-12 08:00:00');

        $this->assertSame([$a->uuid, $b->uuid], $this->uuids($this->list('from=2026-11-10&to=2026-11-11')));
    }

    public function test_to_before_from_is_422(): void
    {
        $this->list('from=2026-11-11&to=2026-11-10')
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors(['to']);
    }

    public function test_range_over_31_days_is_422(): void
    {
        $this->list('from=2026-11-01&to=2026-12-02')->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->list('from=2026-11-01&to=2026-12-01')->assertOk();
    }

    public function test_date_with_a_range_is_422(): void
    {
        $this->list('date=2026-11-10&from=2026-11-10&to=2026-11-11')->assertStatus(422)->assertJsonValidationErrors(['date']);
    }

    public function test_half_a_range_is_422(): void
    {
        $this->list('from=2026-11-10')->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->list('to=2026-11-10')->assertStatus(422)->assertJsonValidationErrors(['from']);
    }

    public function test_bad_date_is_422(): void
    {
        $this->list('date=2026-13-01')->assertStatus(422)->assertJsonValidationErrors(['date']);
        $this->list('from=nope&to=2026-11-10')->assertStatus(422)->assertJsonValidationErrors(['from']);
    }

    public function test_venue_table_and_status_filters(): void
    {
        $otherVenue = DiningVenue::factory()->create();
        $otherTable = RestaurantTable::factory()->create(['dining_venue_id' => $otherVenue->id]);

        $mine    = $this->booking('2026-11-10 16:00:00');
        $pending = $this->booking('2026-11-10 17:00:00', ['status' => ServiceBookingStatus::PENDING]);
        $theirs  = $this->booking('2026-11-10 16:30:00', [], $otherTable);

        $this->assertSame([$mine->uuid, $pending->uuid], $this->uuids($this->list("venue={$this->venue->uuid}")));
        $this->assertSame([$theirs->uuid], $this->uuids($this->list("table={$otherTable->uuid}")));
        $this->assertSame([], $this->uuids($this->list('venue=' . fake()->uuid())));
        $this->assertSame([$mine->uuid, $theirs->uuid], $this->uuids($this->list('status=confirmed')));
        $this->assertSame([$mine->uuid, $theirs->uuid, $pending->uuid], $this->uuids($this->list('status[in]=pending,confirmed')));
        $this->assertSame([], $this->uuids($this->list('status=bogus')));
    }

    public function test_other_bookable_types_are_excluded(): void
    {
        $table = $this->booking('2026-11-10 16:00:00');
        ServiceBooking::factory()->create(['scheduled_at' => CarbonImmutable::parse('2026-11-10 16:00:00', 'UTC')]);
        ServiceBooking::factory()->transfer()->create(['scheduled_at' => CarbonImmutable::parse('2026-11-10 16:00:00', 'UTC')]);

        $this->assertSame([$table->uuid], $this->uuids($this->list()));
    }

    public function test_trashed_venue_still_renders(): void
    {
        $booking = $this->booking('2026-11-10 16:00:00');
        $this->venue->delete();

        $row = $this->list()->assertOk()->json('data.items.0');

        $this->assertSame($booking->uuid, $row['uuid']);
        $this->assertSame($this->venue->uuid, $row['venue']['uuid']);
        $this->assertSame('Rooftop Grill', $row['venue']['name']);
        $this->assertSame([$booking->uuid], $this->uuids($this->list("venue={$this->venue->uuid}")));
    }

    public function test_hard_deleted_table_leaves_a_null_safe_row(): void
    {
        $booking = $this->booking('2026-11-10 16:00:00');
        $this->table->delete();

        $row = $this->list()->assertOk()->json('data.items.0');

        $this->assertSame($booking->uuid, $row['uuid']);
        $this->assertNull($row['table']);
        $this->assertNull($row['venue']);
        $this->assertSame([], $this->uuids($this->list("venue={$this->venue->uuid}")));
    }

    public function test_order_and_sort(): void
    {
        $second = $this->booking('2026-11-10 17:00:00', ['guest_count' => 6]);
        $firstA = $this->booking('2026-11-10 16:00:00', ['guest_count' => 2]);
        $firstB = $this->booking('2026-11-10 16:00:00', ['guest_count' => 4]);

        $this->assertSame([$firstA->uuid, $firstB->uuid, $second->uuid], $this->uuids($this->list()));
        $this->assertSame([$second->uuid, $firstB->uuid, $firstA->uuid], $this->uuids($this->list('sort=guest_count&sort_dir=desc')));
    }

    public function test_row_shape(): void
    {
        $guest   = Guest::factory()->create(['name' => null, 'first_name' => 'Lina', 'last_name' => 'Haddad']);
        $booking = $this->booking('2026-11-10 16:00:00', ['guest' => $guest, 'guest_count' => 3, 'notes' => 'Window seat']);
        $booking->load('reservation');

        $row = $this->list()->assertOk()->json('data.items.0');

        $this->assertSame([
            'uuid', 'status', 'scheduled_at', 'local_date', 'local_time', 'guest_count', 'special_request',
            'venue', 'table', 'guest', 'reservation', 'created_at',
        ], array_keys($row));
        $this->assertSame('confirmed', $row['status']);
        $this->assertTrue(CarbonImmutable::parse($row['scheduled_at'])->equalTo(CarbonImmutable::parse('2026-11-10 16:00:00', 'UTC')));
        $this->assertSame('2026-11-10', $row['local_date']);
        $this->assertSame('19:00', $row['local_time']);
        $this->assertSame(3, $row['guest_count']);
        $this->assertSame('Window seat', $row['special_request']);
        $this->assertSame(['uuid' => $this->venue->uuid, 'name' => 'Rooftop Grill'], $row['venue']);
        $this->assertSame(['uuid' => $this->table->uuid, 'table_number' => 'T-7', 'capacity' => 4], $row['table']);
        $this->assertSame(['uuid' => $guest->uuid, 'name' => 'Lina Haddad'], $row['guest']);
        $this->assertSame(['uuid' => $booking->reservation->uuid, 'booking_code' => $booking->reservation->booking_code], $row['reservation']);
        $this->assertArrayNotHasKey('allowed_statuses', $row);
    }

    public function test_venue_name_follows_the_locale(): void
    {
        $this->booking('2026-11-10 16:00:00');

        $this->withToken($this->token)->withHeader('Accept-Language', 'ar')
            ->getJson('/api/cms/table-reservations')
            ->assertOk()
            ->assertJsonPath('data.items.0.venue.name', 'مشاوي السطح');
    }

    public function test_page_size_defaults_to_50_and_caps_at_100(): void
    {
        $this->list()->assertOk()->assertJsonPath('data.meta.per_page', 50);
        $this->list('per_page=200')->assertOk()->assertJsonPath('data.meta.per_page', 100);
    }

    public function test_query_budget(): void
    {
        $second = DiningVenue::factory()->create();
        $tables = [$this->table, RestaurantTable::factory()->create(['dining_venue_id' => $second->id])];
        foreach (range(0, 3) as $i) {
            $this->booking('2026-11-10 1' . $i . ':00:00', [], $tables[$i % 2]);
        }
        $request = Request::create('/api/cms/table-reservations');

        // count, rows, tables, venues, guests, reservations (D-30 cap 6).
        $this->expectsDatabaseQueryCount(6);

        $page = app(TableReservationService::class)->index([])['data'];
        TableReservationResource::collection($page)->resolve($request);
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/cms/table-reservations')->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_requires_service_requests_view(): void
    {
        $this->app['auth']->forgetGuards();
        $user = User::factory()->create();
        $user->givePermissionTo('tickets.view');

        $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/cms/table-reservations')
            ->assertStatus(403);
    }
}
