<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** PATCH /api/housekeeping/tasks/{task}/assign (Phase 6, HK-02, D-05). */
class AssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function staffToken(string ...$permissions): string
    {
        return $this->staff(...$permissions)->createToken('t')->plainTextToken;
    }

    private function assign(HousekeepingTask $task, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('housekeeping.assign'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/housekeeping/tasks/{$task->uuid}/assign", $body);
    }

    public function test_pending_task_becomes_assigned(): void
    {
        $task     = HousekeepingTask::factory()->create();
        $assignee = User::factory()->create();
        $caller   = $this->staff('housekeeping.assign');

        $this->assign($task, ['user_uuid' => $assignee->uuid], $caller->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.housekeeping_task_assigned'))
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_user.uuid', $assignee->uuid)
            ->assertJsonPath('data.allowed_statuses', ['in_progress', 'cancelled']);

        $row = HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->sole();
        $this->assertSame('pending', $row->from_status);
        $this->assertSame('assigned', $row->to_status);
        $this->assertSame($caller->id, $row->changed_by);
    }

    public function test_assigned_and_in_progress_swap_the_assignee(): void
    {
        $assigned   = HousekeepingTask::factory()->assigned()->create();
        $inProgress = HousekeepingTask::factory()->inProgress()->assigned()->create(['status' => 'in_progress']);
        $newcomer   = User::factory()->create();

        $this->assign($assigned, ['user_uuid' => $newcomer->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_user.uuid', $newcomer->uuid);

        $this->assign($inProgress, ['user_uuid' => $newcomer->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_user.uuid', $newcomer->uuid);

        $this->assertSame(0, HousekeepingTaskStatusHistory::count());
    }

    public function test_closed_task_is_422(): void
    {
        $assignee = User::factory()->create();

        foreach (['done' => HousekeepingTask::factory()->done()->create(), 'cancelled' => HousekeepingTask::factory()->cancelled()->create()] as $status => $task) {
            $this->assign($task, ['user_uuid' => $assignee->uuid])
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'housekeeping_task_closed')
                ->assertJsonPath('context.status', $status);

            $this->assertNull($task->fresh()->assigned_user_id);
            $this->assertSame(HousekeepingTaskStatus::from($status), $task->fresh()->status);
        }
    }

    public function test_unknown_user_is_422(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->assign($task, ['user_uuid' => fake()->uuid()])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['user_uuid']);

        $this->assign($task, [])->assertStatus(422)->assertJsonValidationErrors(['user_uuid']);
        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->fresh()->status);
    }

    public function test_requires_a_token(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->patchJson("/api/housekeeping/tasks/{$task->uuid}/assign", [])->assertStatus(401);
    }

    public function test_kitchen_preset_is_forbidden(): void
    {
        $task    = HousekeepingTask::factory()->create();
        $kitchen = User::factory()->create();
        $kitchen->assignRole('kitchen');

        $this->assign($task, ['user_uuid' => $kitchen->uuid], $kitchen->createToken('t')->plainTextToken)
            ->assertStatus(403);

        $this->assertNull($task->fresh()->assigned_user_id);
    }
}
