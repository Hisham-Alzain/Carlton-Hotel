<?php

namespace Tests\Feature\Rooms;

use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PATCH /api/cms/rooms/{uuid}/status — the audited housekeeping lifecycle
 * (Phase 2, ROOMS-02, D-01..D-07).
 */
class RoomStatusTransitionTest extends TestCase
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

    private function presetUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function patchStatus(string $token, Room $room, array $body, array $headers = [])
    {
        return $this->withToken($token)
            ->withHeaders($headers)
            ->patchJson("/api/cms/rooms/{$room->uuid}/status", $body);
    }

    public function test_housekeeping_preset_marks_an_available_room_dirty(): void
    {
        $room  = Room::factory()->create(['status' => 'available']);
        $token = $this->presetUser('housekeeping')->createToken('t')->plainTextToken;

        $this->patchStatus($token, $room, ['status' => 'dirty'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $room->uuid)
            ->assertJsonPath('data.status', 'dirty')
            ->assertJsonPath('message', __('custom.messages.room_status_updated'));

        $this->assertSame('dirty', $room->fresh()->status->value);
    }

    public function test_reception_preset_can_change_room_status(): void
    {
        $room  = Room::factory()->create(['status' => 'available']);
        $token = $this->presetUser('reception')->createToken('t')->plainTextToken;

        $this->patchStatus($token, $room, ['status' => 'maintenance'])
            ->assertOk()
            ->assertJsonPath('data.status', 'maintenance');
    }

    public function test_rooms_status_alone_suffices_without_cms_edit(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $this->patchStatus($this->staffToken('rooms.status'), $room, ['status' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.status', 'available');
    }

    public static function allowedTransitions(): array
    {
        return [
            'available to dirty'       => ['available', 'dirty'],
            'dirty to available'       => ['dirty', 'available'],
            'available to maintenance' => ['available', 'maintenance'],
            'dirty to maintenance'     => ['dirty', 'maintenance'],
            'maintenance to dirty'     => ['maintenance', 'dirty'],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_succeed(string $from, string $to): void
    {
        $room = Room::factory()->create(['status' => $from]);

        $this->patchStatus($this->staffToken('rooms.status'), $room, ['status' => $to])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', $to);

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => $to]);
        $this->assertDatabaseHas('room_status_history', ['room_id' => $room->id, 'from_status' => $from, 'to_status' => $to]);
    }

    public static function disallowedTransitions(): array
    {
        return [
            'available to available'     => ['available', 'available', ['dirty', 'maintenance']],
            'dirty to dirty'             => ['dirty', 'dirty', ['available', 'maintenance']],
            'maintenance to maintenance' => ['maintenance', 'maintenance', ['dirty']],
            'maintenance to available'   => ['maintenance', 'available', ['dirty']],
        ];
    }

    #[DataProvider('disallowedTransitions')]
    public function test_disallowed_transitions_return_422_with_context(string $from, string $to, array $allowed): void
    {
        $room = Room::factory()->create(['status' => $from]);

        $this->patchStatus($this->staffToken('rooms.status'), $room, ['status' => $to])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'room_status_transition_invalid')
            ->assertJsonPath('context.from', $from)
            ->assertJsonPath('context.to', $to)
            ->assertJsonPath('context.allowed', $allowed);

        $this->assertSame($from, $room->fresh()->status->value);
    }

    public function test_accepted_change_writes_one_history_row_and_denormalized_columns(): void
    {
        $room  = Room::factory()->create(['status' => 'available']);
        $actor = $this->presetUser('housekeeping');

        $this->patchStatus($actor->createToken('t')->plainTextToken, $room, ['status' => 'dirty'])->assertOk();

        $rows = DB::table('room_status_history')->where('room_id', $room->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('available', $rows[0]->from_status);
        $this->assertSame('dirty', $rows[0]->to_status);
        $this->assertSame($actor->id, (int) $rows[0]->changed_by);
        $this->assertNull($rows[0]->reason);
        $this->assertNotNull($rows[0]->created_at);

        $fresh = $room->fresh();
        $this->assertTrue($fresh->status_changed_at->equalTo(now()));
        $this->assertSame($actor->id, (int) $fresh->status_changed_by);
    }

    public function test_reason_is_optional_and_stored_on_the_history_row(): void
    {
        $room = Room::factory()->create(['status' => 'available']);

        $this->patchStatus($this->staffToken('rooms.status'), $room, ['status' => 'dirty', 'reason' => 'Guest spill reported'])
            ->assertOk();

        $this->assertDatabaseHas('room_status_history', [
            'room_id' => $room->id,
            'reason'  => 'Guest spill reported',
        ]);
    }

    public function test_rejected_change_writes_no_history_and_leaves_room_untouched(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $this->patchStatus($this->staffToken('rooms.status'), $room, ['status' => 'dirty'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'room_status_transition_invalid');

        $this->assertSame(0, DB::table('room_status_history')->count());
        $fresh = $room->fresh();
        $this->assertSame('dirty', $fresh->status->value);
        $this->assertNull($fresh->status_changed_at);
        $this->assertNull($fresh->status_changed_by);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $room = Room::factory()->create();

        $this->patchJson("/api/cms/rooms/{$room->uuid}/status", ['status' => 'dirty'])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'unauthorized');

        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public function test_cms_edit_without_rooms_status_returns_403(): void
    {
        $room = Room::factory()->create(['status' => 'available']);

        $this->patchStatus($this->staffToken('cms.edit'), $room, ['status' => 'dirty'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $kitchen = $this->presetUser('kitchen')->createToken('t')->plainTextToken;
        $this->patchStatus($kitchen, $room, ['status' => 'dirty'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'forbidden');

        $this->assertSame('available', $room->fresh()->status->value);
        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing status'         => [[], 'status'],
            'retired in-house value' => [['status' => 'occupied'], 'status'],
            'unknown status'         => [['status' => 'cleaning'], 'status'],
            'reason too long'        => [['status' => 'dirty', 'reason' => null], 'reason'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_returns_422(array $body, string $field): void
    {
        if ($field === 'reason') {
            $body['reason'] = str_repeat('a', 256);
        }
        $room = Room::factory()->create(['status' => 'available']);

        $this->patchStatus($this->staffToken('rooms.status'), $room, $body)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public function test_unknown_or_trashed_room_returns_404(): void
    {
        $token = $this->staffToken('rooms.status');

        $this->withToken($token)
            ->patchJson('/api/cms/rooms/'.Str::uuid().'/status', ['status' => 'dirty'])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $trashed = Room::factory()->create(['status' => 'available']);
        $trashed->delete();

        $this->patchStatus($token, $trashed, ['status' => 'dirty'])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'not_found');

        $this->assertSame(0, DB::table('room_status_history')->count());
    }

    public function test_status_change_never_changes_public_availability(): void
    {
        $type  = RoomType::factory()->create();
        $rooms = Room::factory()->count(2)->create(['room_type_id' => $type->id, 'status' => 'available']);
        $res   = Reservation::factory()->confirmed()->create(['check_in' => '2027-03-12', 'check_out' => '2027-03-14']);
        ReservationRoom::factory()->create(['reservation_id' => $res->id, 'room_type_id' => $type->id, 'room_id' => $rooms[0]->id]);

        $url = "/api/public/availability?room_type_uuid={$type->uuid}&check_in=2027-03-12&check_out=2027-03-14";
        $before = $this->getJson($url)->assertOk()->json('data.rooms_available');
        $this->assertSame(1, $before);

        $token = $this->staffToken('rooms.status');

        $this->patchStatus($token, $rooms[1], ['status' => 'maintenance'])->assertOk();
        $this->assertSame($before, $this->getJson($url)->assertOk()->json('data.rooms_available'));

        $this->patchStatus($token, $rooms[1], ['status' => 'dirty'])->assertOk();
        $this->assertSame($before, $this->getJson($url)->assertOk()->json('data.rooms_available'));
    }

    public function test_transition_error_message_is_translated(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $message = $this->patchStatus($this->staffToken('rooms.status'), $room, ['status' => 'dirty'], ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->json('message');

        $this->assertSame(__('custom.errors.room_status_transition_invalid', [], 'ar'), $message);
        $this->assertNotSame('custom.errors.room_status_transition_invalid', $message);
        $this->assertNotSame(__('custom.errors.room_status_transition_invalid', [], 'en'), $message);
    }
}
