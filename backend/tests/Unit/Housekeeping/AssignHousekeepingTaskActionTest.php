<?php

namespace Tests\Unit\Housekeeping;

use App\Actions\Housekeeping\AssignHousekeepingTaskAction;
use App\Enums\HousekeepingTaskStatus;
use App\Events\HousekeepingTaskChanged;
use App\Exceptions\HousekeepingTaskClosedException;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RecordsRowLocks;
use Tests\TestCase;

/** D-05: the single writer of a task's assignee. */
class AssignHousekeepingTaskActionTest extends TestCase
{
    use RecordsRowLocks, RefreshDatabase;

    private function assign(HousekeepingTask $task, User $assignee, ?User $actor = null): array
    {
        return app(AssignHousekeepingTaskAction::class)->handle($task, $assignee, $actor);
    }

    public function test_pending_task_becomes_assigned_with_history(): void
    {
        $task     = HousekeepingTask::factory()->create();
        $assignee = User::factory()->create();
        $actor    = User::factory()->create();

        $result = $this->assign($task, $assignee, $actor);

        $this->assertSame(200, $result['code']);
        $this->assertSame(HousekeepingTaskStatus::ASSIGNED, $result['data']->status);
        $this->assertSame($assignee->id, $result['data']->assigned_user_id);

        $rows = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(['pending', 'assigned', 'assigned'], [$rows[0]->from_status, $rows[0]->to_status, $rows[0]->reason]);
        $this->assertEquals($actor->id, $rows[0]->changed_by);
    }

    public function test_assigned_task_swaps_the_assignee_without_history(): void
    {
        $task = HousekeepingTask::factory()->assigned()->create();
        $next = User::factory()->create();

        $fresh = $this->assign($task, $next)['data'];

        $this->assertSame(HousekeepingTaskStatus::ASSIGNED, $fresh->status);
        $this->assertSame($next->id, $fresh->assigned_user_id);
        $this->assertSame(0, HousekeepingTaskStatusHistory::count());
    }

    public function test_in_progress_task_swaps_and_stays_in_progress(): void
    {
        $task = HousekeepingTask::factory()->inProgress()->create();
        $next = User::factory()->create();

        $fresh = $this->assign($task, $next)['data'];

        $this->assertSame(HousekeepingTaskStatus::IN_PROGRESS, $fresh->status);
        $this->assertSame($next->id, $fresh->assigned_user_id);
        $this->assertSame(0, HousekeepingTaskStatusHistory::count());
    }

    public function test_closed_tasks_are_refused(): void
    {
        foreach (['done', 'cancelled'] as $status) {
            $task = HousekeepingTask::factory()->create(['status' => $status]);

            try {
                $this->assign($task, User::factory()->create());
                $this->fail("{$status} must be refused");
            } catch (HousekeepingTaskClosedException $e) {
                $this->assertSame('housekeeping_task_closed', $e->errorCode());
                $this->assertSame(422, $e->statusCode());
                $this->assertSame(['status' => $status], $e->context());
            }

            $this->assertNull($task->fresh()->assigned_user_id);
        }
    }

    public function test_room_is_locked_before_the_task(): void
    {
        $task = HousekeepingTask::factory()->create();
        $user = User::factory()->create();

        $locked = $this->lockedSelects(fn () => $this->assign($task, $user));

        $this->assertStringContainsString('from "rooms"', $locked[0]);
        $this->assertStringContainsString('from "housekeeping_tasks"', $locked[1]);
    }

    public function test_event_once_per_success(): void
    {
        Event::fake([HousekeepingTaskChanged::class]);
        $task = HousekeepingTask::factory()->create();

        $this->assign($task, User::factory()->create());
        Event::assertDispatchedTimes(HousekeepingTaskChanged::class, 1);

        $closed = HousekeepingTask::factory()->done()->create();
        try {
            $this->assign($closed, User::factory()->create());
        } catch (HousekeepingTaskClosedException) {
        }
        Event::assertDispatchedTimes(HousekeepingTaskChanged::class, 1);
    }
}
