<?php
namespace Tests\Feature\Staff;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsGroupedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permissions_grouped_by_module(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('staff.manage');
        $token = $actor->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/permissions')
                    ->assertStatus(200)->assertJson(['success' => true]);

        $groups = $res->json('data');
        $this->assertCount(11, $groups);

        // Phase 6 (D-06): the `housekeeping` group holds the task board verbs.
        $hkGroup = collect($groups)->firstWhere('module', 'housekeeping');
        $this->assertNotNull($hkGroup, 'housekeeping group exists');
        $this->assertSame(['housekeeping.assign', 'housekeeping.update', 'housekeeping.view'], collect($hkGroup['permissions'])->sort()->values()->all());

        // Phase 5 (D-05, D-11): the `folios` group gains folios.post and folios.dispute.
        $foliosGroup = collect($groups)->firstWhere('module', 'folios');
        $this->assertNotNull($foliosGroup, 'folios group exists');
        $this->assertSame(['folios.dispute', 'folios.post', 'folios.settle', 'folios.view'], collect($foliosGroup['permissions'])->sort()->values()->all());

        // Phase 4 (D-01): the `guests` group holds the directory read and write.
        $guestsGroup = collect($groups)->firstWhere('module', 'guests');
        $this->assertNotNull($guestsGroup, 'guests group exists');
        $this->assertSame(['guests.edit', 'guests.view'], collect($guestsGroup['permissions'])->sort()->values()->all());

        // Phase 2 (D-06): the new `rooms` group holds only the status verb.
        $roomsGroup = collect($groups)->firstWhere('module', 'rooms');
        $this->assertNotNull($roomsGroup, 'rooms group exists');
        $this->assertSame(['rooms.status'], $roomsGroup['permissions']);

        $srGroup = collect($groups)->firstWhere('module', 'service_requests');
        $this->assertNotNull($srGroup, 'service_requests group exists');
        $this->assertCount(3, $srGroup['permissions']);
        $this->assertSame('service_requests', $srGroup['module']);
    }
}
