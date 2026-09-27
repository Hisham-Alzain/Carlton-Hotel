<?php

namespace Tests\Feature\Housekeeping;

use App\Actions\Housekeeping\UpdateHousekeepingTaskStatusAction;
use App\Enums\HousekeepingTaskStatus;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\RoomStatusHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * D-04: the room board's dirty → available (PATCH /api/cms/rooms/{room}/status)
 * closes the room's open turnover task; the task path never closes twice.
 */
class RoomBoardClosesTurnoverTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->staff = User::factory()->create();
        $this->staff->assignRole('housekeeping');
    }

    private function patchRoom(Room $room, array $body)
    {
        return $this->withToken($this->staff->createToken('t')->plainTextToken)
            ->patchJson("/api/cms/rooms/{$room->uuid}/status", $body);
    }

    private function doneRows(HousekeepingTask $task)
    {
        return HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->where('to_status', 'done')->get();
    }

    public function test_marking_a_dirty_room_available_closes_its_open_turnover(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $task = HousekeepingTask::factory()->create(['room_id' => $room->id]);

        $this->patchRoom($room, ['status' => 'available', 'reason' => 'cleaned'])->assertOk();

        $fresh = $task->fresh();
        $this->assertSame(HousekeepingTaskStatus::DONE, $fresh->status);
        $this->assertSame($this->staff->id, $fresh->completed_by);
        $this->assertNotNull($fresh->completed_at);

        $rows = $this->doneRows($task);
        $this->assertCount(1, $rows);
        $this->assertSame('pending', $rows[0]->from_status);
        $this->assertSame('room_board', $rows[0]->reason);
        $this->assertEquals($this->staff->id, $rows[0]->changed_by);

        $this->assertSame('cleaned', RoomStatusHistory::where('room_id', $room->id)->value('reason'));
    }

    public function test_assigned_and_in_progress_turnovers_are_closed_too(): void
    {
        foreach (['assigned', 'in_progress'] as $status) {
            $room = Room::factory()->create(['status' => 'dirty']);
            $task = HousekeepingTask::factory()->create(['room_id' => $room->id, 'status' => $status]);

            $this->patchRoom($room, ['status' => 'available'])->assertOk();

            $this->assertSame(HousekeepingTaskStatus::DONE, $task->fresh()->status);
            $this->assertSame($status, $this->doneRows($task)[0]->from_status);
        }
    }

    public function test_room_without_an_open_turnover_just_changes(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        $this->patchRoom($room, ['status' => 'available'])->assertOk()->assertJsonPath('data.status', 'available');

        $this->assertSame(0, HousekeepingTask::count());
    }

    public function test_dirty_to_maintenance_keeps_the_turnover_open(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $task = HousekeepingTask::factory()->create(['room_id' => $room->id]);

        $this->patchRoom($room, ['status' => 'maintenance'])->assertOk();

        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->fresh()->status);
    }

    public function test_manual_dirty_creates_no_task(): void
    {
        $room = Room::factory()->create(['status' => 'available']);

        $this->patchRoom($room, ['status' => 'dirty'])->assertOk();

        $this->assertSame(0, HousekeepingTask::count());
    }

    public function test_task_completion_does_not_close_twice(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);
        $task = HousekeepingTask::factory()->inProgress()->create(['room_id' => $room->id]);

        app(UpdateHousekeepingTaskStatusAction::class)->handle($task, HousekeepingTaskStatus::DONE, null, $this->staff);

        $this->assertCount(1, $this->doneRows($task));
        $this->assertNotSame('room_board', $this->doneRows($task)[0]->reason);
        $this->assertSame(1, RoomStatusHistory::where('room_id', $room->id)->count());
        $this->assertSame('available', $room->fresh()->status->value);
    }

    public function test_other_open_task_types_stay_open(): void
    {
        $room     = Room::factory()->create(['status' => 'dirty']);
        $stayover = HousekeepingTask::factory()->stayover()->create(['room_id' => $room->id]);

        $this->patchRoom($room, ['status' => 'available'])->assertOk();

        $this->assertSame(HousekeepingTaskStatus::PENDING, $stayover->fresh()->status);
    }
}
