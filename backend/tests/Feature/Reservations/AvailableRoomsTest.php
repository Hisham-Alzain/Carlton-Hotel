<?php

namespace Tests\Feature\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Booking\ReservationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/cms/reservations/{reservation}/available-rooms (Phase 3, RESV-02, D-04, D-12).
 */
class AvailableRoomsTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_KEYS = ['uuid', 'number', 'floor', 'housekeeping_status', 'assigned'];

    private RoomType $type;
    private Reservation $reservation;
    /** @var array<string, Room> keyed by number */
    private array $rooms = [];

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

    /**
     * Type T: 101 available, 102 dirty, 103 maintenance, 104 inactive,
     * 105 soft-deleted; 201 of another type; R confirmed 2027-04-01..04 with
     * one unassigned line of T.
     */
    private function fixture(): void
    {
        $this->type = RoomType::factory()->create(['name' => ['en' => 'Deluxe', 'ar' => 'ديلوكس']]);
        $this->rooms['101'] = Room::factory()->create(['room_type_id' => $this->type->id, 'number' => '101', 'status' => 'available']);
        $this->rooms['102'] = Room::factory()->create(['room_type_id' => $this->type->id, 'number' => '102', 'status' => 'dirty']);
        $this->rooms['103'] = Room::factory()->create(['room_type_id' => $this->type->id, 'number' => '103', 'status' => 'maintenance']);
        $this->rooms['104'] = Room::factory()->inactive()->create(['room_type_id' => $this->type->id, 'number' => '104']);
        $this->rooms['105'] = Room::factory()->create(['room_type_id' => $this->type->id, 'number' => '105']);
        $this->rooms['105']->delete();
        $this->rooms['201'] = Room::factory()->create(['number' => '201']);

        $this->reservation = $this->stay($this->type, null, 'confirmed', '2027-04-01', '2027-04-04');
    }

    private function stay(RoomType $type, ?Room $room, string $state, string $in, string $out, array $attrs = []): Reservation
    {
        $factory     = $state === 'pending' ? Reservation::factory() : Reservation::factory()->{$state}();
        $reservation = $factory->create(array_merge(['check_in' => $in, 'check_out' => $out], $attrs));
        ReservationRoom::factory()->create([
            'reservation_id' => $reservation->id,
            'room_type_id'   => $type->id,
            'room_id'        => $room?->id,
        ]);
        return $reservation;
    }

    private function list(?string $token = null, ?Reservation $reservation = null)
    {
        $reservation ??= $this->reservation;
        return $this->withToken($token ?? $this->staffToken('reservations.view'))
            ->getJson("/api/cms/reservations/{$reservation->uuid}/available-rooms");
    }

    /** @return list<string> item numbers in response order */
    private function numbers($response): array
    {
        return collect($response->json('data.items'))->pluck('number')->all();
    }

    public function test_lists_free_rooms_with_the_shape(): void
    {
        $this->fixture();

        $response = $this->list()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_type.uuid', $this->type->uuid)
            ->assertJsonPath('data.room_type.name.en', 'Deluxe')
            ->assertJsonPath('data.check_in', '2027-04-01')
            ->assertJsonPath('data.check_out', '2027-04-04');

        $this->assertSame(['room_type', 'check_in', 'check_out', 'items'], array_keys($response->json('data')));
        $this->assertSame(['101', '102'], $this->numbers($response));

        foreach ($response->json('data.items') as $item) {
            $this->assertSame(self::ITEM_KEYS, array_keys($item));
            $this->assertFalse($item['assigned']);
        }

        $items = collect($response->json('data.items'))->keyBy('number');
        $this->assertSame('available', $items['101']['housekeeping_status']);
        $this->assertSame('dirty', $items['102']['housekeeping_status']);
        $this->assertSame($this->rooms['102']->uuid, $items['102']['uuid']);
        $this->assertSame($this->rooms['102']->floor, $items['102']['floor']);
    }

    public function test_assigned_room_is_flagged_and_listed_first(): void
    {
        $this->fixture();
        Room::factory()->create(['room_type_id' => $this->type->id, 'number' => '106']);
        $this->reservation->rooms()->first()->update(['room_id' => $this->rooms['102']->id]);

        $items = $this->list()->assertOk()->json('data.items');

        $this->assertSame(['102', '101', '106'], array_column($items, 'number'));
        $this->assertSame([true, false, false], array_column($items, 'assigned'));
    }

    public function test_holding_stays_exclude_their_rooms(): void
    {
        $this->fixture();
        $other = $this->stay($this->type, $this->rooms['101'], 'pending', '2027-04-02', '2027-04-03');

        $holding = [
            ['status' => ReservationStatus::PENDING, 'hold_expires_at' => null],
            ['status' => ReservationStatus::CONFIRMED, 'hold_expires_at' => null],
            ['status' => ReservationStatus::CHECKED_IN, 'hold_expires_at' => null],
            ['status' => ReservationStatus::PENDING_VERIFICATION, 'hold_expires_at' => now()->addMinutes(5)],
        ];
        foreach ($holding as $attrs) {
            $other->update($attrs);
            $this->assertSame(['102'], $this->numbers($this->list()->assertOk()), $attrs['status']->value.' must hold 101');
        }

        $released = [
            ['status' => ReservationStatus::CANCELLED, 'hold_expires_at' => null],
            ['status' => ReservationStatus::CHECKED_OUT, 'hold_expires_at' => null],
            ['status' => ReservationStatus::PENDING_VERIFICATION, 'hold_expires_at' => now()->subMinute()],
        ];
        foreach ($released as $attrs) {
            $other->update($attrs);
            $this->assertSame(['101', '102'], $this->numbers($this->list()->assertOk()), $attrs['status']->value.' must release 101');
        }
    }

    public function test_back_to_back_stays_do_not_block(): void
    {
        $this->fixture();
        $this->stay($this->type, $this->rooms['101'], 'checkedIn', '2027-03-29', '2027-04-01');
        $this->stay($this->type, $this->rooms['102'], 'confirmed', '2027-04-04', '2027-04-06');

        $this->assertSame(['101', '102'], $this->numbers($this->list()->assertOk()));

        $this->stay($this->type, $this->rooms['101'], 'confirmed', '2027-03-30', '2027-04-02');

        $this->assertSame(['102'], $this->numbers($this->list()->assertOk()));
    }

    public function test_maintenance_inactive_deleted_and_other_type_rooms_are_never_listed(): void
    {
        $this->fixture();

        $uuids = collect($this->list()->assertOk()->json('data.items'))->pluck('uuid')->all();

        foreach (['103', '104', '105', '201'] as $number) {
            $this->assertNotContains($this->rooms[$number]->uuid, $uuids, "room {$number}");
        }
    }

    public function test_no_free_room_returns_an_empty_list(): void
    {
        $this->fixture();
        $this->stay($this->type, $this->rooms['101'], 'confirmed', '2027-04-01', '2027-04-04');
        $this->stay($this->type, $this->rooms['102'], 'checkedIn', '2027-03-30', '2027-04-02');

        $this->list()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', []);
    }

    public function test_null_room_holds_count_against_capacity(): void
    {
        $type = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'number' => '301']);
        Room::factory()->create(['room_type_id' => $type->id, 'number' => '302']);
        $reservation = $this->stay($type, null, 'confirmed', '2027-04-01', '2027-04-04');
        $this->stay($type, null, 'confirmed', '2027-04-01', '2027-04-03');
        $this->stay($type, null, 'pending', '2027-04-02', '2027-04-05');

        $this->list(null, $reservation)->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_type_without_active_rooms_returns_an_empty_list(): void
    {
        $type = RoomType::factory()->create();
        Room::factory()->inactive()->create(['room_type_id' => $type->id]);
        $reservation = $this->stay($type, null, 'confirmed', '2027-04-01', '2027-04-04');

        $this->list(null, $reservation)
            ->assertOk()
            ->assertJsonPath('data.room_type.uuid', $type->uuid)
            ->assertJsonPath('data.items', []);
    }

    public function test_reservation_without_a_room_line_returns_422(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['check_in' => '2027-04-01', 'check_out' => '2027-04-04']);

        $this->list(null, $reservation)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'reservation_state');
    }

    public function test_available_rooms_is_a_pure_read(): void
    {
        $this->fixture();
        $this->reservation->rooms()->first()->update(['room_id' => $this->rooms['101']->id]);

        $counts = fn () => [
            DB::table('reservation_rooms')->count(),
            DB::table('rooms')->count(),
            DB::table('room_status_history')->count(),
            DB::table('activity_log')->count(),
            DB::table('reservations')->count(),
        ];
        $snapshot = fn () => DB::table('rooms')->orderBy('id')->get(['id', 'status', 'updated_at'])->toArray()
            + ['lines' => DB::table('reservation_rooms')->orderBy('id')->pluck('room_id')->all()];

        $token = $this->staffToken('reservations.view');
        $beforeCounts = $counts();
        $beforeRows   = $snapshot();

        $this->list($token)->assertOk();

        $this->assertSame($beforeCounts, $counts());
        $this->assertEquals($beforeRows, $snapshot());
        $this->assertSame(ReservationStatus::CONFIRMED, $this->reservation->fresh()->status);
    }

    public function test_payload_exposes_no_numeric_ids(): void
    {
        $this->fixture();
        $this->reservation->rooms()->first()->update(['room_id' => $this->rooms['101']->id]);

        $data = $this->list()->assertOk()->json('data');

        $keys = [];
        $walk = function ($node) use (&$walk, &$keys) {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
                $walk($value);
            }
        };
        $walk($data);

        foreach (['id', 'room_id', 'room_type_id', 'reservation_id', 'assigned_room_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys);
        }
    }

    public function test_reservations_view_is_enough(): void
    {
        $this->fixture();

        $this->list($this->staffToken('reservations.view'))->assertOk();
        $this->list($this->presetToken('reception'))->assertOk();
    }

    public function test_requires_reservations_view(): void
    {
        $this->fixture();

        foreach ([$this->presetToken('kitchen'), $this->staffToken('cms.edit'), $this->staffToken('reservations.create')] as $token) {
            $this->list($token)
                ->assertForbidden()
                ->assertJsonPath('error_code', 'forbidden');
        }
    }

    public function test_requires_authentication(): void
    {
        $this->fixture();

        $this->getJson("/api/cms/reservations/{$this->reservation->uuid}/available-rooms")
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthorized');

        $guestToken = $this->reservation->guest->createToken('guest')->plainTextToken;
        $this->withToken($guestToken)
            ->getJson("/api/cms/reservations/{$this->reservation->uuid}/available-rooms")
            ->assertUnauthorized();
    }

    public function test_unknown_reservation_returns_404(): void
    {
        $this->withToken($this->staffToken('reservations.view'))
            ->getJson('/api/cms/reservations/'.Str::uuid().'/available-rooms')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'not_found');
    }

    public function test_service_runs_exactly_three_queries(): void
    {
        $type = RoomType::factory()->create();
        $rooms = collect(['401', '402', '403', '404'])
            ->map(fn ($n) => Room::factory()->create(['room_type_id' => $type->id, 'number' => $n]));
        $reservation = $this->stay($type, $rooms[1], 'confirmed', '2027-04-01', '2027-04-04');
        $this->stay($type, $rooms[0], 'confirmed', '2027-04-02', '2027-04-05');
        $service = app(ReservationService::class);

        $this->expectsDatabaseQueryCount(3);
        $result = $service->availableRooms($reservation);

        $this->assertSame(200, $result['code']);
        $this->assertCount(3, $result['data']['items']);
        $this->assertSame('402', $result['data']['items'][0]['number']);
        $this->assertTrue($result['data']['items'][0]['assigned']);
    }

    public function test_query_count_does_not_grow_with_room_count(): void
    {
        $type = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'number' => '500']);
        Room::factory()->create(['room_type_id' => $type->id, 'number' => '501']);
        $reservation = $this->stay($type, null, 'confirmed', '2027-04-01', '2027-04-04');
        $token = $this->staffToken('reservations.view');

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->list($token, $reservation)->assertOk(); // warm-up

        $count = 0;
        $this->list($token, $reservation)->assertOk()->assertJsonCount(2, 'data.items');
        $small = $count;

        for ($n = 2; $n < 30; $n++) {
            $room = Room::factory()->create(['room_type_id' => $type->id, 'number' => (string) (500 + $n)]);
            if ($n % 4 === 0) {
                $this->stay($type, $room, 'confirmed', '2027-04-02', '2027-04-03');
            }
        }

        $count = 0;
        $large = $this->list($token, $reservation)->assertOk();
        $this->assertCount(23, $large->json('data.items'));

        $this->assertSame($small, $count);
    }
}
