<?php

namespace Tests\Unit\Housekeeping;

use App\Actions\Housekeeping\CreateHousekeepingTaskAction;
use App\Actions\Housekeeping\UpdateHousekeepingTaskStatusAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\RoomStatus;
use App\Enums\ServiceRequestStatus;
use App\Events\HousekeepingTaskChanged;
use App\Exceptions\HousekeepingTaskTransitionException;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\RoomStatusHistory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * D-03 / D-05 / D-07 / D-11: the single writer of task status, its room hook
 * and its link to the service request.
 */
class UpdateHousekeepingTaskStatusActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
        $this->actor = User::factory()->create();
    }

    private function move(HousekeepingTask $task, HousekeepingTaskStatus $to, ?string $reason = null, ?User $actor = null): array
    {
        return app(UpdateHousekeepingTaskStatusAction::class)->handle($task, $to, $reason, $actor ?? $this->actor);
    }

    private function fakeFirebase(): FakeFirebaseService
    {
        $fake = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $fake);
        return $fake;
    }

    private function turnoverOn(string $roomStatus, string $taskStatus = 'in_progress'): HousekeepingTask
    {
        $room = Room::factory()->create(['status' => $roomStatus]);

        return HousekeepingTask::factory()->create(['room_id' => $room->id, 'status' => $taskStatus]);
    }

    public function test_each_allowed_transition_writes_one_history_row(): void
    {
        $task = HousekeepingTask::factory()->create();

        foreach ([HousekeepingTaskStatus::ASSIGNED, HousekeepingTaskStatus::IN_PROGRESS, HousekeepingTaskStatus::DONE] as $i => $to) {
            $result = $this->move($task, $to, "step {$i}");
            $this->assertSame(['data', 'code'], array_keys($result));
            $this->assertSame(200, $result['code']);
            $this->assertSame($to, $result['data']->status);
        }

        $rows = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->orderBy('id')->get();
        $this->assertSame(
            [['pending', 'assigned'], ['assigned', 'in_progress'], ['in_progress', 'done']],
            $rows->map(fn ($r) => [$r->from_status, $r->to_status])->all(),
        );
        $this->assertSame('step 2', $rows[2]->reason);
        $this->assertEquals($this->actor->id, $rows[2]->changed_by);

        $other = HousekeepingTask::factory()->stayover()->create();
        $this->move($other, HousekeepingTaskStatus::CANCELLED, 'not needed');
        $this->assertSame(1, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $other->id)->count());
    }

    public function test_in_progress_stamps_started_at_and_self_assigns(): void
    {
        $task = HousekeepingTask::factory()->create(['started_at' => null]);

        $fresh = $this->move($task, HousekeepingTaskStatus::IN_PROGRESS)['data'];
        $this->assertSame('2027-03-12 10:00:00', $fresh->started_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->actor->id, $fresh->assigned_user_id);

        $b        = User::factory()->create();
        $assigned = HousekeepingTask::factory()->assigned($b)->create();
        $this->assertSame($b->id, $this->move($assigned, HousekeepingTaskStatus::IN_PROGRESS)['data']->assigned_user_id);
    }

    public function test_done_stamps_completion_and_clears_the_dedupe_key(): void
    {
        $task = $this->turnoverOn('available');

        $fresh = $this->move($task, HousekeepingTaskStatus::DONE)['data'];

        $this->assertSame('2027-03-12 10:00:00', $fresh->completed_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->actor->id, $fresh->completed_by);
        $this->assertNull($fresh->getRawOriginal('dedupe_key'));

        $again = app(CreateHousekeepingTaskAction::class)->ensureOpen($task->room, HousekeepingTaskType::TURNOVER, [], null);
        $this->assertSame(201, $again['code']);
    }

    public function test_rejected_transitions_write_nothing(): void
    {
        $cases = [
            ['pending', HousekeepingTaskStatus::DONE, ['assigned', 'in_progress', 'cancelled']],
            ['done', HousekeepingTaskStatus::IN_PROGRESS, []],
            ['cancelled', HousekeepingTaskStatus::CANCELLED, []],
        ];

        foreach ($cases as [$from, $to, $allowed]) {
            $task = HousekeepingTask::factory()->create(['status' => $from]);

            try {
                $this->move($task, $to);
                $this->fail("{$from} -> {$to->value} must be rejected");
            } catch (HousekeepingTaskTransitionException $e) {
                $this->assertSame('housekeeping_task_transition_invalid', $e->errorCode());
                $this->assertSame(422, $e->statusCode());
                $this->assertSame(['from' => $from, 'to' => $to->value, 'allowed' => $allowed], $e->context());
            }

            $this->assertSame($from, $task->fresh()->status->value);
            $this->assertSame(0, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->count());
        }
    }

    public function test_turnover_done_moves_a_dirty_room_to_available(): void
    {
        $task = $this->turnoverOn('dirty');

        $this->move($task, HousekeepingTaskStatus::DONE);

        $room = $task->room->fresh();
        $this->assertSame(RoomStatus::AVAILABLE, $room->status);

        $roomRows = RoomStatusHistory::where('room_id', $room->id)->get();
        $this->assertCount(1, $roomRows);
        $this->assertSame('turnover', $roomRows[0]->reason);
        $this->assertEquals($this->actor->id, $roomRows[0]->changed_by);

        $this->assertSame(1, HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->where('to_status', 'done')->count());
    }

    public function test_turnover_done_on_an_available_room_writes_no_room_history(): void
    {
        $task = $this->turnoverOn('available');

        $this->move($task, HousekeepingTaskStatus::DONE);

        $this->assertSame(RoomStatus::AVAILABLE, $task->room->fresh()->status);
        $this->assertSame(0, RoomStatusHistory::count());
    }

    public function test_turnover_done_on_a_maintenance_room_leaves_it(): void
    {
        $task = $this->turnoverOn('maintenance');

        $this->move($task, HousekeepingTaskStatus::DONE);

        $this->assertSame(HousekeepingTaskStatus::DONE, $task->fresh()->status);
        $this->assertSame(RoomStatus::MAINTENANCE, $task->room->fresh()->status);
        $this->assertSame(0, RoomStatusHistory::count());

        $row = DB::table('activity_log')
            ->where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->id)
            ->where('description', 'housekeeping_task.completed')
            ->first();
        $this->assertNotNull($row);
        $this->assertTrue(json_decode($row->properties, true)['room_left_in_maintenance']);
    }

    public function test_other_types_never_touch_the_room(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        foreach ([
            HousekeepingTask::factory()->stayover()->inProgress()->create(['room_id' => $room->id]),
            HousekeepingTask::factory()->inspection()->inProgress()->create(['room_id' => $room->id]),
            HousekeepingTask::factory()->request()->inProgress()->create(['room_id' => $room->id]),
        ] as $task) {
            $this->fakeFirebase();
            $this->move($task, HousekeepingTaskStatus::DONE);
        }

        $this->assertSame(RoomStatus::DIRTY, $room->fresh()->status);
        $this->assertSame(0, RoomStatusHistory::count());
    }

    public function test_cancel_never_touches_the_room(): void
    {
        $room = Room::factory()->create(['status' => 'dirty']);

        foreach (['pending', 'assigned', 'in_progress'] as $status) {
            $task = HousekeepingTask::factory()->create(['room_id' => $room->id, 'status' => $status]);
            $this->move($task, HousekeepingTaskStatus::CANCELLED);
            $this->assertSame(HousekeepingTaskStatus::CANCELLED, $task->fresh()->status);
        }

        $this->assertSame(RoomStatus::DIRTY, $room->fresh()->status);
        $this->assertSame(0, RoomStatusHistory::count());
    }

    public function test_request_task_done_completes_its_request_once(): void
    {
        $fake    = $this->fakeFirebase();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::NEW]);
        $task    = HousekeepingTask::factory()->request($request)->inProgress()->create();

        $this->move($task, HousekeepingTaskStatus::DONE);

        $this->assertSame(ServiceRequestStatus::COMPLETED, $request->fresh()->status);
        $this->assertCount(1, array_filter($fake->mirrors, fn ($m) => $m['document'] === "service_request_{$request->uuid}"));
        $this->assertSame(HousekeepingTaskStatus::DONE, $task->fresh()->status);
    }

    public function test_request_task_done_leaves_a_closed_request_alone(): void
    {
        $fake    = $this->fakeFirebase();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::CANCELLED]);
        $task    = HousekeepingTask::factory()->request($request)->inProgress()->create();

        $this->move($task, HousekeepingTaskStatus::DONE);

        $this->assertSame(ServiceRequestStatus::CANCELLED, $request->fresh()->status);
        // The task's own mirror (plan 06-05) is expected; the request's is not.
        $this->assertSame([], array_values(array_filter($fake->mirrors, fn ($m) => $m['document'] === "service_request_{$request->uuid}")));
    }

    public function test_request_task_cancel_leaves_the_request_alone(): void
    {
        $fake    = $this->fakeFirebase();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::IN_PROGRESS]);
        $task    = HousekeepingTask::factory()->request($request)->create();

        $this->move($task, HousekeepingTaskStatus::CANCELLED);

        $this->assertSame(ServiceRequestStatus::IN_PROGRESS, $request->fresh()->status);
        // The task's own mirror (plan 06-05) is expected; the request's is not.
        $this->assertSame([], array_values(array_filter($fake->mirrors, fn ($m) => $m['document'] === "service_request_{$request->uuid}")));
    }

    public function test_room_is_locked_before_the_task(): void
    {
        $task = $this->turnoverOn('dirty');

        $locked = $this->lockedSelects(fn () => $this->move($task, HousekeepingTaskStatus::DONE));

        $firstRoom = $this->firstIndexOf($locked, 'rooms');
        $firstTask = $this->firstIndexOf($locked, 'housekeeping_tasks');
        $this->assertNotNull($firstRoom);
        $this->assertNotNull($firstTask);
        $this->assertLessThan($firstTask, $firstRoom);
    }

    public function test_event_dispatched_after_each_write(): void
    {
        Event::fake([HousekeepingTaskChanged::class]);
        $task = HousekeepingTask::factory()->create();

        $this->move($task, HousekeepingTaskStatus::ASSIGNED);
        $this->move($task, HousekeepingTaskStatus::IN_PROGRESS);
        Event::assertDispatchedTimes(HousekeepingTaskChanged::class, 2);

        try {
            $this->move($task, HousekeepingTaskStatus::PENDING);
        } catch (HousekeepingTaskTransitionException) {
        }
        Event::assertDispatchedTimes(HousekeepingTaskChanged::class, 2);
    }

    /** @param list<string> $statements */
    private function firstIndexOf(array $statements, string $table): ?int
    {
        foreach ($statements as $i => $sql) {
            if (str_contains($sql, 'from "'.$table.'"')) {
                return $i;
            }
        }

        return null;
    }
}
