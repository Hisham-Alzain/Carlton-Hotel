<?php

namespace Tests\Feature\Rooms;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Operations\FrontDeskService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GET /api/front-desk/room-board (Phase 2, ROOMS-01, D-02, D-11..D-13).
 */
class RoomBoardTest extends TestCase
{
    use RefreshDatabase;

    private const ROW_KEYS = [
        'uuid', 'number', 'floor', 'room_type', 'housekeeping_status', 'status_changed_at',
        'status_changed_by', 'occupancy', 'arriving_today', 'departing_today', 'stayover', 'reservation',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
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

    private function board(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->presetToken('reception'))
            ->getJson('/api/front-desk/room-board'.($query !== '' ? '?'.$query : ''));
    }

    /** @return array<string, array> items keyed by room uuid */
    private function rows($response): array
    {
        return collect($response->json('data.items'))->keyBy('uuid')->all();
    }

    private function book(Room $room, string $state, string $in, string $out, array $attrs = []): Reservation
    {
        $reservation = Reservation::factory()->{$state}()->create(array_merge(['check_in' => $in, 'check_out' => $out], $attrs));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $room->room_type_id,
            'room_id'        => $room->id,
        ]);
        return $reservation;
    }

    private function assertFlags(array $row, string $occupancy, bool $arriving, bool $departing, bool $stayover): void
    {
        $this->assertSame($occupancy, $row['occupancy'], 'occupancy');
        $this->assertSame($arriving, $row['arriving_today'], 'arriving_today');
        $this->assertSame($departing, $row['departing_today'], 'departing_today');
        $this->assertSame($stayover, $row['stayover'], 'stayover');
    }

    public function test_board_returns_every_active_room_with_the_row_shape(): void
    {
        $type   = RoomType::factory()->create(['name' => ['en' => 'Deluxe', 'ar' => 'ديلوكس']]);
        $a      = Room::factory()->create(['room_type_id' => $type->id, 'status' => 'available']);
        $b      = Room::factory()->create(['room_type_id' => $type->id, 'status' => 'maintenance']);
        Room::factory()->inactive()->create(['room_type_id' => $type->id]);
        Room::factory()->create(['room_type_id' => $type->id])->delete();

        $response = $this->board()->assertOk()->assertJsonPath('success', true);
        $items    = $response->json('data.items');

        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(self::ROW_KEYS, array_keys($item));
            $this->assertSame(['uuid', 'name'], array_keys($item['room_type']));
            $this->assertSame($type->uuid, $item['room_type']['uuid']);
            $this->assertSame(['en' => 'Deluxe', 'ar' => 'ديلوكس'], $item['room_type']['name']);
        }

        $rows = $this->rows($response);
        $this->assertSame('available', $rows[$a->uuid]['housekeeping_status']);
        $this->assertSame('maintenance', $rows[$b->uuid]['housekeeping_status']);
        $this->assertSame($a->number, $rows[$a->uuid]['number']);
        $this->assertSame($a->floor, $rows[$a->uuid]['floor']);
    }

    public function test_board_defaults_to_today_and_accepts_a_date(): void
    {
        $room = Room::factory()->create();
        $this->book($room, 'confirmed', '2027-03-11', '2027-03-13');

        $today = $this->board()->assertOk()->assertJsonPath('data.date', '2027-03-10');
        $this->assertFalse($this->rows($today)[$room->uuid]['arriving_today']);

        $tomorrow = $this->board('date=2027-03-11')->assertOk()->assertJsonPath('data.date', '2027-03-11');
        $this->assertTrue($this->rows($tomorrow)[$room->uuid]['arriving_today']);
    }

    public function test_checked_in_stay_marks_room_occupied_and_stayover(): void
    {
        $room = Room::factory()->create();
        $res  = $this->book($room, 'checkedIn', '2027-03-08', '2027-03-12');

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'occupied', false, false, true);
        $this->assertSame($res->uuid, $row['reservation']['uuid']);
        $this->assertSame('checked_in', $row['reservation']['status']);
        $this->assertSame('2027-03-08', $row['reservation']['check_in']);
        $this->assertSame('2027-03-12', $row['reservation']['check_out']);
    }

    public function test_checked_in_on_the_arrival_day_is_occupied_and_arriving_but_not_stayover(): void
    {
        $room = Room::factory()->create();
        $res  = $this->book($room, 'checkedIn', '2027-03-10', '2027-03-13');

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'occupied', true, false, false);
        $this->assertSame($res->uuid, $row['reservation']['uuid']);
    }

    public function test_arrival_today_sets_arriving_flag_and_reservation(): void
    {
        $room = Room::factory()->create();
        $res  = $this->book($room, 'confirmed', '2027-03-10', '2027-03-12');

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'vacant', true, false, false);
        $this->assertSame($res->uuid, $row['reservation']['uuid']);
        $this->assertSame('confirmed', $row['reservation']['status']);
    }

    public function test_departure_today_sets_departing_flag_and_room_reads_vacant(): void
    {
        $room = Room::factory()->create();
        $res  = $this->book($room, 'checkedIn', '2027-03-07', '2027-03-10');

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'vacant', false, true, false);
        $this->assertSame($res->uuid, $row['reservation']['uuid']);
    }

    public function test_back_to_back_turnover_on_the_same_day(): void
    {
        $room    = Room::factory()->create();
        $leaving = $this->book($room, 'checkedIn', '2027-03-07', '2027-03-10');
        $this->book($room, 'confirmed', '2027-03-10', '2027-03-11');

        $other = Room::factory()->create();
        $this->book($other, 'checkedIn', '2027-03-05', '2027-03-09');

        $rows = $this->rows($this->board()->assertOk());

        $this->assertFlags($rows[$room->uuid], 'vacant', true, true, false);
        $this->assertSame($leaving->uuid, $rows[$room->uuid]['reservation']['uuid']);

        $this->assertFlags($rows[$other->uuid], 'vacant', false, false, false);
        $this->assertNull($rows[$other->uuid]['reservation']);
    }

    public function test_cancelled_and_checked_out_reservations_contribute_nothing(): void
    {
        $room = Room::factory()->create();
        $this->book($room, 'cancelled', '2027-03-10', '2027-03-12');
        $this->book($room, 'checkedOut', '2027-03-08', '2027-03-12');

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'vacant', false, false, false);
        $this->assertNull($row['reservation']);
    }

    public function test_reservations_of_other_rooms_change_nothing(): void
    {
        $room  = Room::factory()->create();
        $other = Room::factory()->create();
        $this->book($other, 'checkedIn', '2027-03-08', '2027-03-12');

        // A room-type-level row with no room assigned touches no board row.
        $unassigned = Reservation::factory()->checkedIn()->create(['check_in' => '2027-03-08', 'check_out' => '2027-03-12']);
        ReservationRoom::factory()->create(['reservation_id' => $unassigned->id, 'room_type_id' => $room->room_type_id, 'room_id' => null]);

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'vacant', false, false, false);
        $this->assertNull($row['reservation']);
    }

    public function test_guest_name_falls_back_from_first_last_to_name_to_reservation_last_name(): void
    {
        $full  = Room::factory()->create();
        $named = Room::factory()->create();
        $stub  = Room::factory()->create();

        $this->book($full, 'confirmed', '2027-03-10', '2027-03-12', [
            'guest_id' => Guest::factory()->create(['first_name' => 'Layla', 'last_name' => 'Haddad', 'name' => 'Ignored'])->id,
        ]);
        $this->book($named, 'confirmed', '2027-03-10', '2027-03-12', [
            'guest_id' => Guest::factory()->create(['first_name' => null, 'last_name' => null, 'name' => 'Omar Solo'])->id,
        ]);
        $this->book($stub, 'confirmed', '2027-03-10', '2027-03-12', ['guest_id' => null, 'last_name' => 'Stubbs']);

        $rows = $this->rows($this->board()->assertOk());

        $this->assertSame('Layla Haddad', $rows[$full->uuid]['reservation']['guest_name']);
        $this->assertSame('Omar Solo', $rows[$named->uuid]['reservation']['guest_name']);
        $this->assertSame('Stubbs', $rows[$stub->uuid]['reservation']['guest_name']);
    }

    public function test_rows_are_ordered_by_floor_then_number(): void
    {
        $type = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'floor' => 2, 'number' => '201']);
        Room::factory()->create(['room_type_id' => $type->id, 'floor' => 1, 'number' => '102']);
        Room::factory()->create(['room_type_id' => $type->id, 'floor' => 1, 'number' => '101']);
        Room::factory()->create(['room_type_id' => $type->id, 'floor' => null, 'number' => '001']);

        $numbers = collect($this->board()->assertOk()->json('data.items'))->pluck('number')->all();

        $this->assertSame(['001', '101', '102', '201'], $numbers);
    }

    public function test_empty_board_returns_an_empty_items_array(): void
    {
        // Only inactive / trashed rooms: nothing to list.
        Room::factory()->inactive()->create();
        Room::factory()->create()->delete();

        $response = $this->board()->assertOk()->assertJsonPath('success', true);

        $this->assertSame(['date' => '2027-03-10', 'items' => []], $response->json('data'));
    }

    public function test_room_with_no_reservation_is_vacant_with_null_audit_fields(): void
    {
        $room = Room::factory()->create();

        $row = $this->rows($this->board()->assertOk())[$room->uuid];

        $this->assertFlags($row, 'vacant', false, false, false);
        $this->assertNull($row['reservation']);
        $this->assertNull($row['status_changed_at']);
        $this->assertNull($row['status_changed_by']);
    }

    public function test_status_changed_by_shows_the_changer(): void
    {
        $room  = Room::factory()->create(['status' => 'available']);
        $actor = User::factory()->create(['name' => 'Mona Housekeeper']);
        $actor->assignRole('housekeeping');
        $token = $actor->createToken('t')->plainTextToken;

        $this->withToken($token)->patchJson("/api/cms/rooms/{$room->uuid}/status", ['status' => 'dirty'])->assertOk();

        $row = $this->rows($this->board('', $token)->assertOk())[$room->uuid];

        $this->assertSame('dirty', $row['housekeeping_status']);
        $this->assertSame(['uuid' => $actor->uuid, 'name' => 'Mona Housekeeper'], $row['status_changed_by']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $row['status_changed_at']);
        $this->assertTrue(Carbon::parse($row['status_changed_at'])->equalTo(now()));
    }

    public function test_filters_by_status_floor_and_room_type(): void
    {
        $typeA = RoomType::factory()->create();
        $typeB = RoomType::factory()->create();
        $dirty = Room::factory()->create(['room_type_id' => $typeA->id, 'status' => 'dirty', 'floor' => 1]);
        $upper = Room::factory()->create(['room_type_id' => $typeA->id, 'status' => 'available', 'floor' => 2]);
        $other = Room::factory()->create(['room_type_id' => $typeB->id, 'status' => 'available', 'floor' => 1]);

        $this->assertSame([$dirty->uuid], collect($this->board('status=dirty')->assertOk()->json('data.items'))->pluck('uuid')->all());
        $this->assertSame([$upper->uuid], collect($this->board('floor=2')->assertOk()->json('data.items'))->pluck('uuid')->all());
        $this->assertSame([$other->uuid], collect($this->board("room_type={$typeB->uuid}")->assertOk()->json('data.items'))->pluck('uuid')->all());

        $this->board('room_type=00000000-0000-0000-0000-000000000000')
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_board_exposes_no_numeric_ids_or_guest_contact_details(): void
    {
        $room = Room::factory()->create();
        $this->book($room, 'checkedIn', '2027-03-08', '2027-03-12');
        Room::factory()->create(['status_changed_by' => User::factory()->create()->id]);

        $items = $this->board()->assertOk()->json('data.items');

        $forbidden = ['id', 'room_id', 'room_type_id', 'guest_id', 'reservation_id', 'phone', 'email', 'booking_code', 'status_changed_by_id'];
        $walk = function (array $node) use (&$walk, $forbidden) {
            foreach ($node as $key => $value) {
                if (is_string($key)) {
                    $this->assertNotContains($key, $forbidden, "Board leaks '{$key}'");
                }
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($items);

        $row = collect($items)->firstWhere('uuid', $room->uuid);
        $this->assertSame(['uuid', 'guest_name', 'check_in', 'check_out', 'status'], array_keys($row['reservation']));
    }

    public function test_housekeeping_and_reception_presets_can_read_the_board(): void
    {
        Room::factory()->create();

        $this->board('', $this->presetToken('housekeeping'))->assertOk()->assertJsonCount(1, 'data.items');
        $this->board('', $this->presetToken('reception'))->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_board_requires_rooms_status_or_reservations_view(): void
    {
        $this->board('', $this->staffToken('cms.edit'))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'forbidden');

        $this->board('', $this->presetToken('kitchen'))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_board_requires_authentication(): void
    {
        $this->getJson('/api/front-desk/room-board')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public static function invalidFilters(): array
    {
        return [
            'impossible month'      => ['date=2027-13-01', 'date'],
            'wrong date format'     => ['date=10-03-2027', 'date'],
            'unknown status'        => ['status=cleaning', 'status'],
            'retired in-house value' => ['status=occupied', 'status'],
            'negative floor'        => ['floor=-1', 'floor'],
            'non-integer floor'     => ['floor=abc', 'floor'],
            'over-long room_type'   => ['room_type='.str_repeat('a', 37), 'room_type'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_422(string $query, string $field): void
    {
        $this->board($query)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);
    }

    public function test_board_service_runs_exactly_four_queries(): void
    {
        $changer = User::factory()->create();
        $rooms   = collect();
        foreach (RoomType::factory()->count(3)->create() as $type) {
            $rooms = $rooms->merge(Room::factory()->count(4)->create(['room_type_id' => $type->id]));
        }
        $rooms[0]->forceFill(['status' => 'dirty', 'status_changed_at' => now(), 'status_changed_by' => $changer->id])->save();
        $this->book($rooms[1], 'checkedIn', '2027-03-08', '2027-03-12');
        $this->book($rooms[5], 'confirmed', '2027-03-10', '2027-03-12');
        $this->book($rooms[9], 'checkedIn', '2027-03-07', '2027-03-10');
        $service = app(FrontDeskService::class);

        $this->expectsDatabaseQueryCount(4);
        $result = $service->board([]);

        $this->assertSame(200, $result['code']);
        $this->assertCount(12, $result['data']['items']);
    }

    public function test_board_query_count_does_not_grow_with_room_count(): void
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $token = $this->presetToken('reception');
        $this->board('', $token)->assertOk(); // warm-up

        $changer = User::factory()->create();
        $type    = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'status_changed_by' => $changer->id, 'status_changed_at' => now()]);
        $this->book(Room::factory()->create(['room_type_id' => $type->id]), 'checkedIn', '2027-03-08', '2027-03-12');

        $count = 0;
        $this->board('', $token)->assertOk()->assertJsonCount(2, 'data.items');
        $small = $count;

        foreach (RoomType::factory()->count(3)->create() as $i => $t) {
            for ($n = 0; $n < 9; $n++) {
                $room = Room::factory()->create([
                    'room_type_id'      => $t->id,
                    'status_changed_by' => User::factory()->create()->id,
                    'status_changed_at' => now(),
                ]);
                if ($n % 3 === 0) {
                    $this->book($room, 'checkedIn', '2027-03-08', '2027-03-12');
                }
            }
        }
        Room::factory()->create(['room_type_id' => $type->id]);

        $count = 0;
        $this->board('', $token)->assertOk()->assertJsonCount(30, 'data.items');
        $large = $count;

        $this->assertSame($small, $large);
    }
}
