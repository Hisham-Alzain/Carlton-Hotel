<?php

namespace Tests\Feature\Rooms;

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
 * GET /api/front-desk/availability-grid (Phase 2, ROOMS-03, D-08, D-09, D-11).
 */
class AvailabilityGridTest extends TestCase
{
    use RefreshDatabase;

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

    private function grid(string $query = '', ?string $token = null)
    {
        return $this->withToken($token ?? $this->presetToken('reception'))
            ->getJson('/api/front-desk/availability-grid'.($query !== '' ? '?'.$query : ''));
    }

    /** @return array<string, array> room types keyed by uuid */
    private function types($response): array
    {
        return collect($response->json('data.room_types'))->keyBy('uuid')->all();
    }

    private function book(RoomType $type, ?Room $room, string $state, string $in, string $out, array $attrs = []): Reservation
    {
        $factory     = $state === 'pending' ? Reservation::factory() : Reservation::factory()->{$state}(); // pending is the default state
        $reservation = $factory->create(array_merge(['check_in' => $in, 'check_out' => $out], $attrs));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room?->id,
        ]);
        return $reservation;
    }

    public function test_grid_defaults_to_14_days_from_today(): void
    {
        $active   = RoomType::factory()->create();
        $inactive = RoomType::factory()->inactive()->create();
        Room::factory()->create(['room_type_id' => $active->id]);

        $response = $this->grid()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.from', '2027-03-10')
            ->assertJsonPath('data.days', 14);

        $types = $this->types($response);
        $this->assertArrayHasKey($active->uuid, $types);
        $this->assertArrayNotHasKey($inactive->uuid, $types);

        $dates = array_column($types[$active->uuid]['cells'], 'date');
        $this->assertCount(14, $dates);
        $this->assertSame('2027-03-10', $dates[0]);
        $this->assertSame('2027-03-23', $dates[13]);
    }

    public function test_cells_count_booked_rooms_per_night(): void
    {
        $type  = RoomType::factory()->create();
        $rooms = Room::factory()->count(3)->create(['room_type_id' => $type->id]);
        Room::factory()->inactive()->create(['room_type_id' => $type->id]);
        Room::factory()->create(['room_type_id' => $type->id])->delete();

        $this->book($type, $rooms[0], 'confirmed', '2027-03-08', '2027-03-12'); // crosses window start
        $this->book($type, null, 'checkedIn', '2027-03-10', '2027-03-11');     // null room_id still counts
        $this->book($type, $rooms[1], 'confirmed', '2027-03-15', '2027-03-20'); // crosses window end

        $row = $this->types($this->grid('days=7')->assertOk())[$type->uuid];

        $this->assertSame(3, $row['total']);
        $expectedBooked = [
            '2027-03-10' => 2, '2027-03-11' => 1, '2027-03-12' => 0, '2027-03-13' => 0,
            '2027-03-14' => 0, '2027-03-15' => 1, '2027-03-16' => 1,
        ];
        $this->assertSame(array_keys($expectedBooked), array_column($row['cells'], 'date'));
        foreach ($row['cells'] as $cell) {
            $this->assertSame($expectedBooked[$cell['date']], $cell['booked'], "booked on {$cell['date']}");
            $this->assertSame(3 - $expectedBooked[$cell['date']], $cell['free'], "free on {$cell['date']}");
            $this->assertSame(0, $cell['out_of_order']);
        }
    }

    public function test_grid_free_agrees_with_public_availability_for_every_cell(): void
    {
        $a = RoomType::factory()->create(['sort_order' => 1]);
        $b = RoomType::factory()->create(['sort_order' => 2]);
        $c = RoomType::factory()->create(['sort_order' => 3]); // active, zero rooms

        $aRooms = Room::factory()->count(3)->create(['room_type_id' => $a->id]);
        $aRooms[2]->forceFill(['status' => 'maintenance'])->save();
        $bRoom  = Room::factory()->create(['room_type_id' => $b->id]);

        $this->book($a, $aRooms[0], 'confirmed', '2027-03-08', '2027-03-12');
        $this->book($a, $aRooms[1], 'checkedIn', '2027-03-09', '2027-03-11');
        $this->book($a, $aRooms[1], 'cancelled', '2027-03-11', '2027-03-15');
        $this->book($a, $aRooms[2], 'checkedOut', '2027-03-10', '2027-03-13');
        $this->book($a, $aRooms[1], 'pendingVerification', '2027-03-12', '2027-03-15'); // live hold
        $this->book($a, $aRooms[0], 'expiredHold', '2027-03-12', '2027-03-14');         // expired hold
        $this->book($a, null, 'confirmed', '2027-03-14', '2027-03-20');                  // null room, crosses end
        $this->book($a, null, 'pending', '2027-03-13', '2027-03-14');
        $this->book($b, $bRoom, 'confirmed', '2027-03-11', '2027-03-13');
        $this->book($b, $bRoom, 'confirmed', '2027-03-12', '2027-03-14');                // overbooked on 12th
        $this->book($c, null, 'confirmed', '2027-03-10', '2027-03-12');                  // type with no rooms

        $grid = $this->grid('days=7')->assertOk()->json('data.room_types');
        $this->assertSame([$a->uuid, $b->uuid, $c->uuid], array_column($grid, 'uuid'));

        $checked = 0;
        foreach ($grid as $type) {
            foreach ($type['cells'] as $cell) {
                $next = Carbon::parse($cell['date'])->addDay()->toDateString();
                $available = $this->getJson("/api/public/availability?room_type_uuid={$type['uuid']}&check_in={$cell['date']}&check_out={$next}")
                    ->assertOk()
                    ->json('data.rooms_available');

                $this->assertSame($available, $cell['free'], "type {$type['uuid']} on {$cell['date']}");
                $this->assertGreaterThanOrEqual(0, $cell['free']);
                $checked++;
            }
        }
        $this->assertSame(21, $checked);

        $bCells = collect($this->types($this->grid('days=7'))[$b->uuid]['cells'])->keyBy('date');
        $this->assertSame(2, $bCells['2027-03-12']['booked']);
        $this->assertSame(0, $bCells['2027-03-12']['free']);
    }

    public function test_maintenance_rooms_are_reported_but_never_subtracted(): void
    {
        $type  = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'status' => 'available']);
        Room::factory()->create(['room_type_id' => $type->id, 'status' => 'maintenance']);
        Room::factory()->inactive()->create(['room_type_id' => $type->id, 'status' => 'maintenance']); // inactive: not counted

        $row = $this->types($this->grid()->assertOk())[$type->uuid];

        $this->assertSame(2, $row['total']);
        foreach ($row['cells'] as $cell) {
            $this->assertSame(['date' => $cell['date'], 'free' => 2, 'booked' => 0, 'out_of_order' => 1], $cell);
        }
    }

    public function test_status_changes_never_change_free_or_booked(): void
    {
        $type  = RoomType::factory()->create();
        $rooms = Room::factory()->count(3)->create(['room_type_id' => $type->id, 'status' => 'available']);
        $this->book($type, $rooms[0], 'confirmed', '2027-03-11', '2027-03-14');

        $reception = $this->presetToken('reception');
        $before    = $this->types($this->grid('', $reception)->assertOk())[$type->uuid]['cells'];

        $status = $this->staffToken('rooms.status');
        $this->withToken($status)->patchJson("/api/cms/rooms/{$rooms[1]->uuid}/status", ['status' => 'maintenance'])->assertOk();
        $this->withToken($status)->patchJson("/api/cms/rooms/{$rooms[2]->uuid}/status", ['status' => 'dirty'])->assertOk();

        $after = $this->types($this->grid('', $reception)->assertOk())[$type->uuid]['cells'];

        $this->assertCount(14, $after);
        foreach ($after as $i => $cell) {
            $this->assertSame($before[$i]['free'], $cell['free']);
            $this->assertSame($before[$i]['booked'], $cell['booked']);
            $this->assertSame($before[$i]['out_of_order'] + 1, $cell['out_of_order']);
        }
    }

    public static function boundaries(): array
    {
        return [
            'days 0'                  => ['days=0', 422, 'days', null],
            'days 32'                 => ['days=32', 422, 'days', null],
            'days not a number'       => ['days=abc', 422, 'days', null],
            'days decimal'            => ['days=1.5', 422, 'days', null],
            'days 1'                  => ['days=1', 200, null, 1],
            'days 31'                 => ['days=31', 200, null, 31],
            'impossible from'         => ['from=2027-02-30', 422, 'from', null],
            'wrong from format'       => ['from=10-03-2027', 422, 'from', null],
            'from today minus 366'    => ['from=2026-03-09', 422, 'from', null],
            'from today minus 365'    => ['from=2026-03-10', 200, null, 14],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_grid_validation_boundaries(string $query, int $status, ?string $field, ?int $cells): void
    {
        $type = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id]);

        $response = $this->grid($query)->assertStatus($status);

        if ($status === 422) {
            $response->assertJsonPath('error_code', 'validation_failed')->assertJsonValidationErrors([$field]);
            return;
        }

        $this->assertCount($cells, $response->json('data.room_types.0.cells'));
    }

    public function test_grid_exposes_counts_only(): void
    {
        $type = RoomType::factory()->create();
        $room = Room::factory()->create(['room_type_id' => $type->id]);
        $this->book($type, $room, 'checkedIn', '2027-03-09', '2027-03-12');

        $data = $this->grid()->assertOk()->json('data');

        $this->assertSame(['from', 'days', 'room_types'], array_keys($data));
        foreach ($data['room_types'] as $rt) {
            $this->assertSame(['uuid', 'name', 'total', 'cells'], array_keys($rt));
            foreach ($rt['cells'] as $cell) {
                $this->assertSame(['date', 'free', 'booked', 'out_of_order'], array_keys($cell));
                $this->assertIsInt($cell['free']);
                $this->assertIsInt($cell['booked']);
                $this->assertIsInt($cell['out_of_order']);
            }
        }
        $json = json_encode($data);
        foreach (['"id"', 'room_id', 'room_type_id', 'guest', 'booking_code', 'reservation'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_grid_requires_reservations_view(): void
    {
        $this->grid('', $this->presetToken('housekeeping'))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->grid('', $this->staffToken('cms.edit'))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');
    }

    public function test_grid_requires_authentication(): void
    {
        $this->getJson('/api/front-desk/availability-grid')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');
    }

    public function test_availability_grid_service_runs_exactly_four_queries(): void
    {
        foreach (RoomType::factory()->count(3)->create() as $type) {
            $rooms = Room::factory()->count(2)->create(['room_type_id' => $type->id]);
            $this->book($type, $rooms[0], 'confirmed', '2027-03-12', '2027-03-15');
        }
        $rooms[1]->forceFill(['status' => 'maintenance'])->save();
        $service = app(FrontDeskService::class);

        $this->expectsDatabaseQueryCount(4);
        $result = $service->availabilityGrid(['days' => 31]);

        $this->assertSame(200, $result['code']);
        $this->assertCount(3, $result['data']['room_types']);
        foreach ($result['data']['room_types'] as $rt) {
            $this->assertCount(31, $rt['cells']);
        }
    }

    public function test_availability_grid_query_count_does_not_grow_with_days_or_room_types(): void
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $token = $this->presetToken('reception');
        $this->grid('', $token)->assertOk(); // warm-up

        $first = RoomType::factory()->create();
        $room  = Room::factory()->create(['room_type_id' => $first->id, 'status' => 'maintenance']);
        $this->book($first, $room, 'confirmed', '2027-03-10', '2027-03-12');

        $count = 0;
        $this->grid('days=1', $token)->assertOk()->assertJsonCount(1, 'data.room_types');
        $small = $count;

        foreach (RoomType::factory()->count(3)->create() as $type) {
            $rooms = Room::factory()->count(3)->create(['room_type_id' => $type->id]);
            $this->book($type, $rooms[0], 'confirmed', '2027-03-15', '2027-03-20');
            $this->book($type, null, 'checkedIn', '2027-03-09', '2027-03-11');
            $rooms[2]->forceFill(['status' => 'maintenance'])->save();
        }

        $count = 0;
        $this->grid('days=31', $token)->assertOk()->assertJsonCount(4, 'data.room_types');
        $large = $count;

        $this->assertSame($small, $large);
    }
}
