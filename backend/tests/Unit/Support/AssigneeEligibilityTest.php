<?php

namespace Tests\Unit\Support;

use App\Exceptions\AssigneeNotEligibleException;
use App\Models\User;
use App\Support\AssigneeEligibility;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * D-09: the shared assignee gate — active, staff/super_admin, holds the work permission.
 */
class AssigneeEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function assertRefused(User $user, string $permission): void
    {
        try {
            AssigneeEligibility::assert($user, $permission);
            $this->fail('ineligible assignee accepted');
        } catch (AssigneeNotEligibleException $e) {
            $this->assertSame('assignee_not_eligible', $e->errorCode());
            $this->assertSame(422, $e->statusCode());
            $this->assertSame(['user_uuid' => $user->uuid, 'required_permission' => $permission], $e->context());
        }
    }

    public function test_direct_permission_passes(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tickets.respond');

        AssigneeEligibility::assert($user, 'tickets.respond');
        $this->addToAssertionCount(1);
    }

    public function test_role_permission_passes(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('housekeeping', 'users'));

        AssigneeEligibility::assert($user, 'housekeeping.update');
        $this->addToAssertionCount(1);
    }

    public function test_inactive_user_is_refused(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->givePermissionTo('tickets.respond');

        $this->assertRefused($user, 'tickets.respond');
    }

    public function test_non_staff_type_is_refused(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('tickets.respond');
        DB::table('users')->where('id', $user->id)->update(['type' => 'integration']);

        $this->assertRefused($user->fresh(), 'tickets.respond');
    }

    public function test_missing_permission_is_refused(): void
    {
        $this->assertRefused(User::factory()->create(), 'service_requests.update');
    }

    public function test_super_admin_without_explicit_permission_passes(): void
    {
        AssigneeEligibility::assert(User::factory()->superAdmin()->create(), 'housekeeping.update');
        $this->addToAssertionCount(1);
    }

    public function test_inactive_super_admin_is_refused(): void
    {
        $this->assertRefused(User::factory()->superAdmin()->create(['is_active' => false]), 'housekeeping.update');
    }
}
