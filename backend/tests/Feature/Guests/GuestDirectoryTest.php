<?php

namespace Tests\Feature\Guests;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Guest\GuestService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /api/guests — the staff guest directory (Phase 4, GUEST-01, D-01, D-02, D-03).
 */
class GuestDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private const ROW_KEYS = [
        'uuid', 'name', 'first_name', 'last_name', 'phone', 'phone_country', 'phone_verified',
        'email', 'email_verified', 'preferred_locale', 'stay_status', 'current_reservation', 'created_at',
        // Phase 9.1 (D-12), additive.
        'account_status', 'account_deleted_at',
    ];

    private int $roomSeq = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00')); // T = 2027-03-10
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        return $user->createToken('t')->plainTextToken;
    }

    private function presetToken(string $role): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user->createToken('t')->plainTextToken;
    }

    private function stay(Guest $g, string $state, string $in, string $out, ?string $roomNumber = null): Reservation
    {
        $type        = RoomType::factory()->create();
        $reservation = Reservation::factory()->create([
            'guest_id' => $g->id, 'status' => $state, 'check_in' => $in, 'check_out' => $out,
        ]);
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $roomNumber === null ? null
                : Room::factory()->create(['room_type_id' => $type->id, 'number' => $roomNumber])->id,
        ]);
        return $reservation;
    }

    private function guest(string $first, string $last, array $attrs = []): Guest
    {
        return Guest::factory()->create(array_merge([
            'first_name' => $first, 'last_name' => $last, 'name' => "{$first} {$last}",
        ], $attrs));
    }

    private function list(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('guests.view'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/guests' . ($query === '' ? '' : "?{$query}"));
    }

    private function uuids(string $query = ''): array
    {
        return array_column($this->list($query)->assertOk()->json('data.items'), 'uuid');
    }

    private function row(Guest $guest, string $query = ''): array
    {
        $row = collect($this->list($query)->assertOk()->json('data.items'))->firstWhere('uuid', $guest->uuid);
        $this->assertNotNull($row, "guest {$guest->name} listed");
        return $row;
    }

    public function test_row_shape(): void
    {
        $guest = $this->guest('Layla', 'Haddad', ['phone_verified_at' => now()]);
        $stay  = $this->stay($guest, 'confirmed', '2027-03-12', '2027-03-14', '812');

        $row = $this->row($guest);

        $this->assertSame(self::ROW_KEYS, array_keys($row));
        $this->assertSame(['uuid', 'booking_code', 'status', 'check_in', 'check_out', 'room_number'], array_keys($row['current_reservation']));
        $this->assertSame($stay->uuid, $row['current_reservation']['uuid']);
        $this->assertSame('812', $row['current_reservation']['room_number']);
        $this->assertSame('2027-03-12', $row['current_reservation']['check_in']);
        $this->assertTrue($row['phone_verified']);
        $this->assertFalse($row['email_verified']);
        $this->assertSame('upcoming', $row['stay_status']);
        $this->assertArrayNotHasKey('notes', $row);
        $this->assertArrayNotHasKey('preferences', $row);
        $this->assertArrayNotHasKey('id', $row);
    }

    public function test_default_order(): void
    {
        $layla = $this->guest('Layla', 'Haddad');
        $ahmad = $this->guest('Ahmad', 'Haddad');
        $omar  = $this->guest('Omar', 'Aziz');
        $rami1 = $this->guest('Rami', 'Nasser');
        $rami2 = $this->guest('Rami', 'Nasser');

        $this->assertSame([$omar->uuid, $ahmad->uuid, $layla->uuid, $rami1->uuid, $rami2->uuid], $this->uuids());
    }

    public function test_explicit_sort_keeps_id_tie_break(): void
    {
        $a = $this->guest('A', 'Zed');
        $b = $this->guest('B', 'Young');
        $c = $this->guest('C', 'Xavier');
        Guest::whereKey([$a->id, $b->id])->update(['created_at' => '2027-03-01 00:00:00']);
        Guest::whereKey($c->id)->update(['created_at' => '2027-03-05 00:00:00']);

        $this->assertSame([$c->uuid, $a->uuid, $b->uuid], $this->uuids('sort=created_at&sort_dir=desc'));
        $this->assertSame([$a->uuid, $b->uuid, $c->uuid], $this->uuids('sort=created_at&sort_dir=asc'));

        // An unknown sort column keeps the default order (last_name, name, id).
        $this->assertSame([$c->uuid, $b->uuid, $a->uuid], $this->uuids('sort=phone'));
    }

    public function test_search_by_name_phone_and_email(): void
    {
        $layla = $this->guest('Layla', 'Haddad', ['phone' => '+963933111222', 'email' => 'layla@example.com']);
        $omar  = $this->guest('Omar', 'Aziz', ['phone' => '+963944555666', 'email' => 'omar.aziz@hotel.test']);

        $this->assertSame([$layla->uuid], $this->uuids('search=layla'));
        $this->assertSame([$layla->uuid], $this->uuids('search=HADDAD'));
        $this->assertSame([$omar->uuid], $this->uuids('search=' . urlencode('4455')));
        $this->assertSame([$omar->uuid], $this->uuids('search=hotel.test'));
    }

    public function test_search_encoding(): void
    {
        $arabic = $this->guest('ليلى', 'حداد');
        $this->guest('Layla', 'Haddad');
        $xy  = $this->guest('X', 'One', ['email' => 'x_y@example.com']);
        $xay = $this->guest('X', 'Two', ['email' => 'xay@example.com']);

        $this->assertSame([$arabic->uuid], $this->uuids('search=' . urlencode('ليلى')));
        $found = $this->uuids('search=x_y');
        $this->assertContains($xy->uuid, $found);
        $this->assertNotContains($xay->uuid, $found);
    }

    public function test_field_filters(): void
    {
        $a = $this->guest('A', 'One', ['phone' => '+963911000001', 'email' => 'a@alpha.test', 'preferred_locale' => 'en']);
        $b = $this->guest('B', 'Two', ['phone' => '+963911000002', 'email' => 'b@beta.test', 'preferred_locale' => 'ar']);
        $c = $this->guest('C', 'Three', ['phone' => '+963911000003', 'email' => 'c@beta.test', 'preferred_locale' => 'fr']);

        $this->assertSame([$a->uuid], $this->uuids('phone[eq]=' . urlencode('+963911000001')));
        $this->assertSame([$c->uuid, $b->uuid], $this->uuids('email[like]=beta'));
        $this->assertSame([$a->uuid, $b->uuid], $this->uuids('preferred_locale[in]=en,ar'));
    }

    public function test_each_stay_status_filter(): void
    {
        $inHouse   = $this->guest('In', 'House');
        $departing = $this->guest('De', 'Parting');
        $arriving  = $this->guest('Ar', 'Riving');
        $upcoming  = $this->guest('Up', 'Coming');
        $past      = $this->guest('Pa', 'St');
        $none      = $this->guest('No', 'Ne');

        $this->stay($inHouse, 'checked_in', '2027-03-08', '2027-03-12', '101');
        $this->stay($departing, 'checked_in', '2027-03-07', '2027-03-10', '102');
        $this->stay($arriving, 'confirmed', '2027-03-10', '2027-03-12');
        $this->stay($upcoming, 'pending', '2027-03-15', '2027-03-17');
        $this->stay($past, 'checked_out', '2027-03-01', '2027-03-03');
        $this->stay($past, 'cancelled', '2027-03-20', '2027-03-22');

        $expect = [
            'in_house'  => [$inHouse, $departing],
            'departing' => [$departing],
            'arriving'  => [$arriving],
            'upcoming'  => [$upcoming],
            'past'      => [$past],
            'none'      => [$none],
        ];

        foreach ($expect as $status => $guests) {
            $found = $this->uuids("stay_status={$status}");
            $this->assertEqualsCanonicalizing(array_map(fn ($g) => $g->uuid, $guests), $found, $status);
        }

        foreach (['in_house' => $inHouse, 'departing' => $departing, 'arriving' => $arriving, 'upcoming' => $upcoming, 'past' => $past, 'none' => $none] as $status => $guest) {
            $this->assertSame($status, $this->row($guest)['stay_status'], "row for {$status}");
        }
    }

    public function test_row_precedence_for_back_to_back_stays(): void
    {
        $guest   = $this->guest('Back', 'ToBack');
        $current = $this->stay($guest, 'checked_in', '2027-03-07', '2027-03-10', '201');
        $this->stay($guest, 'confirmed', '2027-03-10', '2027-03-12');

        $row = $this->row($guest);
        $this->assertSame('departing', $row['stay_status']);
        $this->assertSame($current->uuid, $row['current_reservation']['uuid']);
        $this->assertContains($guest->uuid, $this->uuids('stay_status=departing'));
        $this->assertContains($guest->uuid, $this->uuids('stay_status=arriving'));

        $current->update(['check_out' => '2027-03-11']);
        $this->assertSame('in_house', $this->row($guest)['stay_status']);
    }

    public function test_hotel_local_today_decides_the_status(): void
    {
        $this->travelTo(Carbon::parse('2027-03-09 16:30:00'));
        $guest = $this->guest('Time', 'Zone');
        $this->stay($guest, 'confirmed', '2027-03-10', '2027-03-12');

        config(['hotel.timezone' => 'Asia/Tokyo']);
        $this->assertSame('arriving', $this->row($guest)['stay_status']);
        $this->assertSame([$guest->uuid], $this->uuids('stay_status=arriving'));

        config(['hotel.timezone' => 'UTC']);
        $this->assertSame('upcoming', $this->row($guest)['stay_status']);
        $this->assertSame([$guest->uuid], $this->uuids('stay_status=upcoming'));
    }

    public function test_unknown_stay_status_is_rejected(): void
    {
        $this->guest('Any', 'One');

        $this->list('stay_status=bogus')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('stay_status');
        $this->list('stay_status[]=in_house')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors('stay_status');

        $this->list('stay_status=')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_pagination_boundaries(): void
    {
        Guest::factory()->count(16)->create();

        $this->list()->assertOk()
            ->assertJsonPath('data.meta.per_page', 15)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonCount(15, 'data.items');
        $this->list('page=2')->assertOk()->assertJsonCount(1, 'data.items');
        $this->list('per_page=1')->assertOk()->assertJsonCount(1, 'data.items');
        $this->list('per_page=100')->assertOk()->assertJsonCount(16, 'data.items');
        $this->list('per_page=101')->assertOk()->assertJsonPath('data.meta.per_page', 100);
        $this->list('per_page=0')->assertOk()->assertJsonPath('data.meta.per_page', 15)->assertJsonCount(15, 'data.items');
    }

    public function test_empty_directory(): void
    {
        $this->list()->assertOk()->assertJsonPath('data.items', [])->assertJsonPath('data.meta.total', 0);

        $guest = $this->guest('Solo', 'Guest');
        $row   = $this->row($guest);
        $this->assertSame('none', $row['stay_status']);
        $this->assertNull($row['current_reservation']);
    }

    public function test_overdue_arrival_row_reads_arriving(): void
    {
        $guest = $this->guest('Late', 'Arrival');
        $this->stay($guest, 'confirmed', '2027-03-09', '2027-03-12');

        $this->assertSame('arriving', $this->row($guest)['stay_status']);
    }

    public function test_current_reservation_is_the_next_arrival_not_the_latest_booking(): void
    {
        $guest = $this->guest('Next', 'Arrival');
        $this->stay($guest, 'confirmed', '2027-04-01', '2027-04-03');
        $next = $this->stay($guest, 'pending', '2027-03-12', '2027-03-14');

        $this->assertSame($next->uuid, $this->row($guest)['current_reservation']['uuid']);
    }

    private function furnish(): void
    {
        foreach (['In', 'Up', 'Past'] as $i => $name) {
            $guest = $this->guest($name, 'Guest');
            match ($i) {
                0 => $this->stay($guest, 'checked_in', '2027-03-08', '2027-03-12', '301'),
                1 => $this->stay($guest, 'confirmed', '2027-03-15', '2027-03-17', '302'),
                2 => $this->stay($guest, 'checked_out', '2027-03-01', '2027-03-03', '303'),
            };
        }
    }

    public function test_service_path_is_five_queries(): void
    {
        $this->furnish();

        $this->expectsDatabaseQueryCount(5);

        $page = app(GuestService::class)->index([], null, 15)['data'];
        $this->assertSame(3, $page->total());
    }

    public function test_service_path_is_five_queries_with_a_stay_status_filter(): void
    {
        $this->furnish();

        $this->expectsDatabaseQueryCount(5);

        $page = app(GuestService::class)->index(['stay_status' => 'in_house'], null, 15)['data'];
        $this->assertSame(1, $page->total());
    }

    public function test_gates(): void
    {
        $guest = $this->guest('Gate', 'Keeper');

        $this->getJson('/api/guests')->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->withToken($guest->createToken('t')->plainTextToken)->getJson('/api/guests')->assertStatus(401);
        $this->list('', $this->staffToken('reservations.view'))->assertStatus(403)->assertJsonPath('error_code', 'forbidden');
        $this->list('', $this->presetToken('housekeeping'))->assertStatus(403);
        $this->list('', $this->presetToken('reception'))->assertOk();
        $this->list('', $this->presetToken('concierge'))->assertOk();
    }
}
