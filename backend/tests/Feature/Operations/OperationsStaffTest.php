<?php

namespace Tests\Feature\Operations;

use App\Models\User;
use App\Services\Operations\OperationsStaffService;
use App\Http\Resources\Operations\OperationsStaffResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * GET /api/operations/staff (Phase 7, OPS-02; D-23, council A5): the
 * assignee picker. Same gate as the queue index; unpaginated, capped at 200;
 * rows are exactly {uuid, name, type, departments[]}.
 */
class OperationsStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function viewer(string $permission = 'tickets.view'): User
    {
        return User::factory()->create(['name' => 'Zz Viewer'])->givePermissionTo($permission);
    }

    private function list(array $query = [], ?User $as = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $as ??= $this->viewer();

        return $this->withToken($as->createToken('t')->plainTextToken)
            ->getJson('/api/operations/staff' . ($query ? '?' . http_build_query($query) : ''));
    }

    private function names(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data.items'))->pluck('name')->all();
    }

    private function role(string $role, string $name, array $attrs = []): User
    {
        return User::factory()->create(['name' => $name] + $attrs)->assignRole($role);
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/operations/staff')->assertStatus(401)->assertJson(['error_code' => 'unauthorized']);
    }

    public function test_forbidden_without_a_queue_view_permission(): void
    {
        $this->list([], User::factory()->create()->givePermissionTo('cms.view'))
            ->assertStatus(403)->assertJson(['error_code' => 'forbidden']);
    }

    public function test_each_queue_view_permission_opens_the_directory(): void
    {
        foreach (['service_requests.view', 'tickets.view', 'housekeeping.view'] as $permission) {
            $this->list([], $this->viewer($permission))->assertOk()->assertJson(['success' => true]);
        }
    }

    public function test_rows_have_exactly_the_directory_shape(): void
    {
        $this->role('reception', 'Alia Front');

        $items = $this->list()->assertOk()->json('data.items');

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame(['departments', 'name', 'type', 'uuid'], collect(array_keys($item))->sort()->values()->all());
            $this->assertIsArray($item['departments']);
            $this->assertArrayNotHasKey('email', $item);
            $this->assertArrayNotHasKey('roles', $item);
            $this->assertArrayNotHasKey('permissions', $item);
        }
    }

    public function test_only_active_staff_and_super_admins_are_listed(): void
    {
        User::factory()->create(['name' => 'Active Staff']);
        User::factory()->superAdmin()->create(['name' => 'Boss Admin']);
        User::factory()->create(['name' => 'Gone Staff', 'is_active' => false]);
        User::factory()->create(['name' => 'Bot Integration', 'type' => 'integration']);

        $names = $this->names($this->list());

        $this->assertContains('Active Staff', $names);
        $this->assertContains('Boss Admin', $names);
        $this->assertNotContains('Gone Staff', $names);
        $this->assertNotContains('Bot Integration', $names);
    }

    public function test_rows_are_sorted_by_name(): void
    {
        User::factory()->create(['name' => 'Charlie']);
        User::factory()->create(['name' => 'Adam']);
        User::factory()->create(['name' => 'Bella']);

        $this->assertSame(['Adam', 'Bella', 'Charlie', 'Zz Viewer'], $this->names($this->list()));
    }

    public function test_type_filters_to_the_work_permission(): void
    {
        $this->role('events', 'Events Person');
        $this->role('kitchen', 'Kitchen Cook');
        $this->role('housekeeping', 'House Keeper');
        User::factory()->create(['name' => 'Direct Responder'])->givePermissionTo('tickets.respond');
        User::factory()->superAdmin()->create(['name' => 'Boss Admin']);

        $this->assertSame(['Boss Admin', 'Direct Responder', 'Events Person'], $this->names($this->list(['type' => 'tickets'])));
        $this->assertSame(['Boss Admin', 'House Keeper'], $this->names($this->list(['type' => 'housekeeping-tasks'])));

        $this->list(['type' => 'bogus'])->assertStatus(422)->assertJson(['error_code' => 'validation_failed']);
    }

    public function test_permission_filter_accepts_only_work_permissions(): void
    {
        $this->role('events', 'Events Person');
        $this->role('kitchen', 'Kitchen Cook');

        $this->assertSame(['Events Person'], $this->names($this->list(['permission' => 'tickets.respond'])));

        foreach (['tickets.view', 'staff.manage', 'nope'] as $bad) {
            $this->list(['permission' => $bad])
                ->assertStatus(422)
                ->assertJson(['error_code' => 'validation_failed'])
                ->assertJsonValidationErrors('permission', 'errors');
        }
    }

    public function test_type_and_permission_apply_together(): void
    {
        $this->role('events', 'Events Person');
        $this->role('housekeeping', 'House Keeper');

        $this->assertSame([], $this->names($this->list(['type' => 'tickets', 'permission' => 'housekeeping.update'])));
    }

    public function test_department_filter_uses_role_names(): void
    {
        $this->role('reception', 'Front Desk');
        $this->role('kitchen', 'Kitchen Cook');
        User::factory()->superAdmin()->create(['name' => 'Plain Admin']);
        User::factory()->superAdmin()->create(['name' => 'Reception Admin'])->assignRole('reception');

        $this->assertSame(['Front Desk', 'Reception Admin'], $this->names($this->list(['department' => 'reception'])));

        foreach (['sales', 'maintenance'] as $department) {
            $this->list(['department' => $department])
                ->assertOk()
                ->assertJsonPath('data.items', [])
                ->assertJsonPath('data.meta.count', 0)
                ->assertJsonPath('data.meta.truncated', false);
        }

        $this->list(['department' => 'bogus'])->assertStatus(422)->assertJson(['error_code' => 'validation_failed']);
    }

    public function test_search_matches_names_and_escapes_wildcards(): void
    {
        User::factory()->create(['name' => 'Alia']);
        User::factory()->create(['name' => 'Khalil']);
        User::factory()->create(['name' => 'Omar']);
        User::factory()->create(['name' => '100% Sure']);

        $this->assertSame(['Alia', 'Khalil'], $this->names($this->list(['search' => 'ali'])));
        $this->assertSame(['100% Sure'], $this->names($this->list(['search' => '%'])));
        $this->assertSame([], $this->names($this->list(['search' => '_'])));
    }

    public function test_departments_is_always_an_array(): void
    {
        $none = User::factory()->create(['name' => 'No Dept'])->assignRole('content_editor');
        $one  = $this->role('reception', 'One Dept');
        $two  = $this->role('reception', 'Two Dept')->assignRole('concierge');

        $rows = collect($this->list()->assertOk()->json('data.items'))->keyBy('uuid');

        $this->assertSame([], $rows[$none->uuid]['departments']);
        $this->assertSame(['reception'], $rows[$one->uuid]['departments']);
        $this->assertEqualsCanonicalizing(['reception', 'concierge'], $rows[$two->uuid]['departments']);
    }

    public function test_list_is_capped_at_200(): void
    {
        User::factory()->count(201)->create()->each->assignRole('kitchen');

        $this->list(['department' => 'kitchen'])
            ->assertOk()
            ->assertJsonCount(200, 'data.items')
            ->assertJsonPath('data.meta.count', 200)
            ->assertJsonPath('data.meta.truncated', true);
    }

    public function test_short_list_is_not_truncated(): void
    {
        User::factory()->count(3)->create()->each->assignRole('concierge');

        $this->list(['department' => 'concierge'])
            ->assertOk()
            ->assertJsonPath('data.meta.count', 3)
            ->assertJsonPath('data.meta.truncated', false);
    }

    public function test_directory_stays_within_four_queries(): void
    {
        $this->role('events', 'Events Person');
        $this->role('reception', 'Front Desk')->givePermissionTo('tickets.respond');
        app(\Spatie\Permission\PermissionRegistrar::class)->getPermissions(); // warm the cache like a running app

        $service = app(OperationsStaffService::class);

        $this->expectsDatabaseQueryCount(4);

        $result = $service->index(['type' => 'tickets', 'department' => 'reception']);
        OperationsStaffResource::collection($result['data']['items'])->resolve();

        $this->assertCount(1, $result['data']['items']);
    }

    public function test_no_queue_staff_alias(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($this->viewer()->createToken('t')->plainTextToken)
            ->getJson('/api/operations/queue/staff')
            ->assertStatus(404);
    }
}
