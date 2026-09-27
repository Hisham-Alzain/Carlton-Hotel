<?php

namespace Tests\Feature\Housekeeping;

use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskStatusHistory;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** PATCH /api/housekeeping/tasks/{task}/status (Phase 6, HK-03, D-05, D-07). */
class StatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['hotel.timezone' => 'Asia/Damascus']);
        $this->travelTo(Carbon::parse('2027-03-12 10:00:00'));
    }

    private function preset(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function staffToken(string ...$permissions): string
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user->createToken('t')->plainTextToken;
    }

    private function move(HousekeepingTask $task, array $body, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staffToken('housekeeping.update'))
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/housekeeping/tasks/{$task->uuid}/status", $body);
    }

    public function test_pending_to_in_progress_self_assigns(): void
    {
        $task   = HousekeepingTask::factory()->create();
        $caller = $this->preset('housekeeping');

        $this->move($task, ['status' => 'in_progress'], $caller->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.housekeeping_task_status_updated'))
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_user.uuid', $caller->uuid)
            ->assertJsonPath('data.started_at', '2027-03-12T10:00:00+00:00')
            ->assertJsonPath('data.allowed_statuses', ['done', 'cancelled']);
    }

    public function test_completing_a_turnover_frees_the_room(): void
    {
        $room   = Room::factory()->create(['status' => 'dirty']);
        $task   = HousekeepingTask::factory()->inProgress()->create(['room_id' => $room->id]);
        $caller = $this->preset('housekeeping');
        $token  = $caller->createToken('t')->plainTextToken;

        $this->move($task, ['status' => 'done', 'reason' => 'cleaned'], $token)
            ->assertOk()
            ->assertJsonPath('message', __('custom.messages.housekeeping_task_status_updated'))
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.room.status', 'available')
            ->assertJsonPath('data.allowed_statuses', []);

        $row = collect(
            $this->withToken($token)->getJson('/api/front-desk/room-board?date=2027-03-12')->assertOk()->json('data.items')
        )->firstWhere('uuid', $room->uuid);

        $this->assertSame('available', $row['housekeeping_status']);
        $this->assertSame($caller->id, $task->fresh()->completed_by);
        $this->assertSame('cleaned', HousekeepingTaskStatusHistory::where('housekeeping_task_id', $task->id)->latest('id')->value('reason'));
    }

    public function test_maintenance_room_stays_in_maintenance(): void
    {
        $room = Room::factory()->create(['status' => 'maintenance']);
        $task = HousekeepingTask::factory()->inProgress()->create(['room_id' => $room->id]);

        $this->move($task, ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.room.status', 'maintenance');

        $this->assertSame('maintenance', $room->fresh()->status->value);
    }

    public function test_disallowed_transition_is_422_with_context(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->move($task, ['status' => 'done'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'housekeeping_task_transition_invalid')
            ->assertJsonPath('context.from', 'pending')
            ->assertJsonPath('context.to', 'done')
            ->assertJsonPath('context.allowed', ['assigned', 'in_progress', 'cancelled']);

        $this->assertSame('pending', $task->fresh()->status->value);
        $this->assertSame(0, HousekeepingTaskStatusHistory::count());
    }

    public function test_bad_status_and_long_reason_are_422(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->move($task, ['status' => 'finished'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonValidationErrors(['status']);

        $this->move($task, ['status' => 'cancelled', 'reason' => str_repeat('r', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->move($task, [])->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->assertSame('pending', $task->fresh()->status->value);
    }

    public function test_requires_a_token(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->patchJson("/api/housekeeping/tasks/{$task->uuid}/status", ['status' => 'cancelled'])->assertStatus(401);
    }

    public function test_reception_preset_cannot_change_status(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->move($task, ['status' => 'cancelled'], $this->preset('reception')->createToken('t')->plainTextToken)
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $task->fresh()->status->value);
    }

    public function test_housekeeping_preset_can_change_status(): void
    {
        $task = HousekeepingTask::factory()->create();

        $this->move($task, ['status' => 'cancelled'], $this->preset('housekeeping')->createToken('t')->plainTextToken)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_kitchen_and_concierge_presets_are_forbidden_everywhere(): void
    {
        $task = HousekeepingTask::factory()->create();
        $room = Room::factory()->create();

        foreach (['kitchen', 'concierge'] as $role) {
            $token = $this->preset($role)->createToken('t')->plainTextToken;

            $this->withToken($token)->getJson('/api/housekeeping/tasks')->assertStatus(403);
            $this->withToken($token)->getJson("/api/housekeeping/tasks/{$task->uuid}")->assertStatus(403);
            $this->withToken($token)->postJson('/api/housekeeping/tasks', ['room_uuid' => $room->uuid, 'type' => 'turnover'])->assertStatus(403);
            $this->withToken($token)->patchJson("/api/housekeeping/tasks/{$task->uuid}/assign", ['user_uuid' => User::factory()->create()->uuid])->assertStatus(403);
            $this->withToken($token)->patchJson("/api/housekeeping/tasks/{$task->uuid}/status", ['status' => 'cancelled'])->assertStatus(403);
        }

        $this->assertSame('pending', $task->fresh()->status->value);
        $this->assertSame(1, HousekeepingTask::count());
    }
}
