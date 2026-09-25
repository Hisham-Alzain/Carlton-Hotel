<?php

namespace Tests\Unit\Rooms;

use App\Actions\Cms\UpdateRoomStatusAction;
use App\Enums\RoomStatus;
use App\Exceptions\RoomStatusTransitionException;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The D-04 transition table and the single writer, without HTTP.
 */
class UpdateRoomStatusActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-03-10 09:00:00'));
    }

    public function test_enum_values_are_available_dirty_maintenance(): void
    {
        $this->assertSame(['available', 'dirty', 'maintenance'], RoomStatus::values());
    }

    public function test_transition_table_matches_d04(): void
    {
        $allowed = [
            'available>dirty', 'dirty>available', 'available>maintenance',
            'dirty>maintenance', 'maintenance>dirty',
        ];

        foreach (RoomStatus::cases() as $from) {
            foreach (RoomStatus::cases() as $to) {
                $this->assertSame(
                    in_array("{$from->value}>{$to->value}", $allowed, true),
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value}",
                );
            }
        }
    }

    public function test_handle_returns_data_and_code_and_writes_history(): void
    {
        $room = Room::factory()->create(['status' => 'available']);
        $user = User::factory()->create();

        $result = app(UpdateRoomStatusAction::class)->handle($room, RoomStatus::DIRTY, 'note', $user);

        $this->assertSame(['data', 'code'], array_keys($result));
        $this->assertSame(200, $result['code']);
        $this->assertInstanceOf(Room::class, $result['data']);
        $this->assertSame(RoomStatus::DIRTY, $result['data']->status);
        $this->assertSame($user->id, (int) $result['data']->status_changed_by);
        $this->assertTrue($result['data']->status_changed_at->equalTo(now()));

        $this->assertSame(1, DB::table('room_status_history')->count());
        $this->assertDatabaseHas('room_status_history', [
            'room_id'     => $room->id,
            'from_status' => 'available',
            'to_status'   => 'dirty',
            'changed_by'  => $user->id,
            'reason'      => 'note',
        ]);
    }

    public function test_history_chains_across_successive_changes(): void
    {
        $room   = Room::factory()->create(['status' => 'available']);
        $user   = User::factory()->create();
        $action = app(UpdateRoomStatusAction::class);

        // A stale in-memory model must not matter: the action re-reads the locked row.
        $action->handle($room, RoomStatus::MAINTENANCE, null, $user);
        $action->handle($room, RoomStatus::DIRTY, null, $user);
        $action->handle($room, RoomStatus::AVAILABLE, null, $user);

        $this->assertSame(
            [['available', 'maintenance'], ['maintenance', 'dirty'], ['dirty', 'available']],
            DB::table('room_status_history')->orderBy('id')->get()
                ->map(fn ($r) => [$r->from_status, $r->to_status])->all(),
        );
    }

    public function test_handle_throws_domain_exception_and_writes_nothing(): void
    {
        $room = Room::factory()->create(['status' => 'maintenance']);
        $user = User::factory()->create();

        try {
            app(UpdateRoomStatusAction::class)->handle($room, RoomStatus::MAINTENANCE, null, $user);
            $this->fail('Expected RoomStatusTransitionException');
        } catch (RoomStatusTransitionException $e) {
            $this->assertSame('room_status_transition_invalid', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
            $this->assertSame(
                ['from' => 'maintenance', 'to' => 'maintenance', 'allowed' => ['dirty']],
                $e->context(),
            );
        }

        $this->assertSame(0, DB::table('room_status_history')->count());
        $fresh = $room->fresh();
        $this->assertSame(RoomStatus::MAINTENANCE, $fresh->status);
        $this->assertNull($fresh->status_changed_at);
        $this->assertNull($fresh->status_changed_by);
    }
}
