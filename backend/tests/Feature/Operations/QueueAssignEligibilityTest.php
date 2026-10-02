<?php

namespace Tests\Feature\Operations;

use App\Actions\Operations\AssignRequestAction;
use App\Contracts\FirebaseServiceInterface;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RecordsRowLocks;
use Tests\Support\FakeFirebaseService;
use Tests\TestCase;

/**
 * D-09 assignee eligibility on the pre-existing assign verbs, plus the
 * service-request assign lock and closed check (council A1, PR-1, PR-5).
 */
class QueueAssignEligibilityTest extends TestCase
{
    use RefreshDatabase;
    use RecordsRowLocks;

    private FakeFirebaseService $firebase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->firebase = new FakeFirebaseService();
        $this->app->instance(FirebaseServiceInterface::class, $this->firebase);
    }

    private function staff(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function srAssign(ServiceRequest $request, User $assignee, ?string $token = null)
    {
        return $this->withToken($token ?? $this->staff('service_requests.assign')->createToken('t')->plainTextToken)
            ->withHeaders(['Accept-Language' => 'en'])
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/assign", ['user_uuid' => $assignee->uuid]);
    }

    private function mirrorsFor(string $document): int
    {
        return collect($this->firebase->mirrors)->where('document', $document)->count();
    }

    public function test_sr_assign_to_a_user_without_the_work_permission_is_422(): void
    {
        $request  = ServiceRequest::factory()->create();
        $assignee = $this->staff('service_requests.view');

        $this->srAssign($request, $assignee)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible')
            ->assertJsonPath('context.user_uuid', $assignee->uuid)
            ->assertJsonPath('context.required_permission', 'service_requests.update');

        $this->assertNull($request->fresh()->assigned_user_id);
        $this->assertSame(0, $this->mirrorsFor("service_request_{$request->uuid}"));
    }

    public function test_sr_assign_to_an_inactive_user_is_422(): void
    {
        $request  = ServiceRequest::factory()->create();
        $assignee = User::factory()->withPermissions('service_requests.update')->create(['is_active' => false]);

        $this->srAssign($request, $assignee)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible');

        $this->assertNull($request->fresh()->assigned_user_id);
    }

    public function test_sr_assign_to_a_super_admin_succeeds(): void
    {
        $request  = ServiceRequest::factory()->create();
        $assignee = User::factory()->superAdmin()->create();

        $this->srAssign($request, $assignee)
            ->assertOk()
            ->assertJsonPath('data.assigned_user_uuid', $assignee->uuid);
    }

    public function test_sr_assign_to_an_eligible_user_succeeds_and_mirrors_once(): void
    {
        $request  = ServiceRequest::factory()->create();
        $assignee = User::factory()->withPermissions('service_requests.update')->create();

        $this->srAssign($request, $assignee)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_user_uuid', $assignee->uuid);

        $this->assertSame($assignee->id, $request->fresh()->assigned_user_id);
        $this->assertSame(1, $this->mirrorsFor("service_request_{$request->uuid}"));
    }

    public function test_sr_assign_on_a_closed_request_is_422(): void
    {
        $assignee = User::factory()->withPermissions('service_requests.update')->create();

        foreach ([ServiceRequestStatus::COMPLETED, ServiceRequestStatus::CANCELLED] as $status) {
            $request = ServiceRequest::factory()->create(['status' => $status]);

            $this->srAssign($request, $assignee)
                ->assertStatus(422)
                ->assertJsonPath('error_code', 'service_request_closed')
                ->assertJsonPath('context.status', $status->value);

            $this->assertNull($request->fresh()->assigned_user_id);
            $this->assertSame(0, $this->mirrorsFor("service_request_{$request->uuid}"));
        }
    }

    /**
     * SQLite never serialises concurrent writers; this proves only that the
     * SQL carries `for update` (council A9 — MySQL-only guarantee).
     */
    public function test_sr_assign_locks_the_request_row(): void
    {
        $request  = ServiceRequest::factory()->create();
        $eligible = User::factory()->withPermissions('service_requests.update')->create();
        $actor    = $this->staff('service_requests.assign');

        $this->assertLocksRow('service_requests', fn () => app(AssignRequestAction::class)->handle($request, $eligible, $actor));
    }

    public function test_hk_assign_rejects_a_reception_preset_user(): void
    {
        $task      = HousekeepingTask::factory()->create();
        $reception = User::factory()->create();
        $reception->assignRole('reception');
        $token     = $this->staff('housekeeping.assign')->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/housekeeping/tasks/{$task->uuid}/assign", ['user_uuid' => $reception->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible')
            ->assertJsonPath('context.required_permission', 'housekeeping.update');

        $this->withToken($token)
            ->patchJson("/api/operations/queue/housekeeping-tasks/{$task->uuid}/assign", ['user_uuid' => $reception->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'assignee_not_eligible')
            ->assertJsonPath('context.required_permission', 'housekeeping.update');

        $this->assertSame(HousekeepingTaskStatus::PENDING, $task->fresh()->status);
        $this->assertNull($task->fresh()->assigned_user_id);
    }

    public function test_hk_assign_accepts_a_housekeeping_preset_user(): void
    {
        $task = HousekeepingTask::factory()->create();
        $hk   = User::factory()->create();
        $hk->assignRole('housekeeping');

        $this->withToken($this->staff('housekeeping.assign')->createToken('t')->plainTextToken)
            ->patchJson("/api/housekeeping/tasks/{$task->uuid}/assign", ['user_uuid' => $hk->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned');

        $this->assertSame($hk->id, $task->fresh()->assigned_user_id);
    }

    public function test_hk_closed_check_runs_before_eligibility(): void
    {
        $task = HousekeepingTask::factory()->done()->create();

        $this->withToken($this->staff('housekeeping.assign')->createToken('t')->plainTextToken)
            ->patchJson("/api/housekeeping/tasks/{$task->uuid}/assign", ['user_uuid' => User::factory()->create()->uuid])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'housekeeping_task_closed');
    }

    public function test_requires_a_token(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->patchJson("/api/operations/queue/service-requests/{$request->uuid}/assign", ['user_uuid' => User::factory()->create()->uuid])
            ->assertStatus(401);
    }

    public function test_actor_without_assign_permission_is_forbidden(): void
    {
        $request  = ServiceRequest::factory()->create();
        $assignee = User::factory()->withPermissions('service_requests.update')->create();

        $this->srAssign($request, $assignee, $this->staff('service_requests.view')->createToken('t')->plainTextToken)
            ->assertStatus(403);

        $this->assertNull($request->fresh()->assigned_user_id);
    }

    public function test_missing_user_uuid_is_422(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->withToken($this->staff('service_requests.assign')->createToken('t')->plainTextToken)
            ->patchJson("/api/operations/queue/service-requests/{$request->uuid}/assign", [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }
}
