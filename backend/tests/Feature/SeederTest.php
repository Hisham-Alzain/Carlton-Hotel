<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_26_permissions_seeded(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $expected = [
            'reservations.view', 'reservations.create', 'reservations.cancel',
            'folios.view', 'folios.settle',
            // Phase 5 (D-05): posting charges and credits
            'folios.post',
            // Phase 5 (D-11): staff dispute raise/resolve/reject
            'folios.dispute',
            // cms.restore and cms.purge are the recycle bin, split off cms.edit
            // because undoing a delete and destroying a record permanently are
            // not edits — see RolesAndPermissionsSeeder and RecycleBinTest.
            'cms.view', 'cms.edit', 'cms.restore', 'cms.purge',
            // Phase 2 (D-06): the `rooms` group, independent of cms.edit.
            'rooms.status',
            'service_requests.view', 'service_requests.assign', 'service_requests.update',
            'tickets.view', 'tickets.assign', 'tickets.respond',
            'pricing.edit', 'reports.view', 'staff.manage',
            // Phase 4 (D-01): the guests group
            'guests.view', 'guests.edit',
            // Phase 6 (D-06): the housekeeping task board
            'housekeeping.view', 'housekeeping.assign', 'housekeeping.update',
        ];
        foreach ($expected as $p) {
            $this->assertDatabaseHas('permissions', ['name' => $p, 'guard_name' => 'users']);
        }
        $this->assertCount(26, Permission::where('guard_name', 'users')->get());
    }

    public function test_housekeeping_permissions_are_granted_to_housekeeping_and_reception(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $roles   = Role::where('guard_name', 'users')->with('permissions')->get();
        $holders = fn (string $permission) => $roles
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->contains($permission))
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['housekeeping', 'reception'], $holders('housekeeping.view'));
        $this->assertSame(['housekeeping', 'reception'], $holders('housekeeping.assign'));
        $this->assertSame(['housekeeping'], $holders('housekeeping.update'));
        $this->assertSame(['concierge', 'housekeeping', 'kitchen', 'reception'], $holders('service_requests.update'));
        $this->assertSame(['concierge'], $holders('service_requests.assign'));

        // D-06: the other operational presets are unchanged.
        $perms = fn (string $name) => $roles->firstWhere('name', $name)->permissions->pluck('name')->sort()->values()->all();
        $this->assertSame(['service_requests.update', 'service_requests.view'], $perms('kitchen'));
        $this->assertSame(['guests.edit', 'guests.view', 'service_requests.assign', 'service_requests.update', 'service_requests.view'], $perms('concierge'));
        $this->assertSame(['service_requests.view', 'tickets.assign', 'tickets.respond', 'tickets.view'], $perms('events'));
    }

    public function test_all_7_role_presets_seeded(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        foreach (['reception', 'kitchen', 'housekeeping', 'concierge', 'events', 'content_editor', 'content_manager'] as $r) {
            $this->assertDatabaseHas('roles', ['name' => $r, 'guard_name' => 'users']);
        }
        $this->assertCount(7, Role::where('guard_name', 'users')->get());
    }

    public function test_content_editor_preset_grants_the_cms_permissions(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $role = Role::where(['name' => 'content_editor', 'guard_name' => 'users'])->firstOrFail();

        // Restore but not purge: the editor who deleted a record is the one who
        // wants it back, while emptying the bin takes the row, its cascade
        // children and their files with no way back.
        $this->assertSame(
            ['cms.edit', 'cms.restore', 'cms.view'],
            $role->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_content_manager_preset_is_the_only_one_that_may_purge(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $holders = Role::where('guard_name', 'users')->with('permissions')->get()
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->contains('cms.purge'))
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame(['content_manager'], $holders);
    }

    public function test_every_seeded_permission_is_reachable_through_some_role(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // A permission no preset grants can only be held via a direct grant,
        // which is how cms.edit shipped unusable. Guard against a repeat.
        $granted = Role::where('guard_name', 'users')->with('permissions')->get()
            ->flatMap->permissions->pluck('name')->unique();

        $ungrantable = Permission::where('guard_name', 'users')->pluck('name')
            ->reject(fn ($p) => $granted->contains($p))
            // Deliberately role-less: super-admin-only capabilities.
            ->reject(fn ($p) => in_array($p, ['staff.manage', 'pricing.edit', 'reports.view'], true))
            ->values();

        $this->assertSame([], $ungrantable->all(), 'Permissions granted by no role preset: '.$ungrantable->implode(', '));
    }

    public function test_housekeeping_and_reception_presets_grant_rooms_status(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $holders = Role::where('guard_name', 'users')->with('permissions')->get()
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->contains('rooms.status'))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['housekeeping', 'reception'], $holders);
    }

    public function test_reception_and_concierge_presets_grant_the_guest_permissions(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        foreach (['guests.view', 'guests.edit'] as $permission) {
            $holders = Role::where('guard_name', 'users')->with('permissions')->get()
                ->filter(fn (Role $role) => $role->permissions->pluck('name')->contains($permission))
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            $this->assertSame(['concierge', 'reception'], $holders, $permission);
        }
    }

    public function test_folio_posting_follows_the_folios_settle_holders(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $holders = fn (string $permission) => Role::where('guard_name', 'users')->with('permissions')->get()
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->contains($permission))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($holders('folios.settle'), $holders('folios.post'));
        $this->assertSame(['reception'], $holders('folios.post'));
    }

    public function test_folio_dispute_follows_the_folios_post_holders(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $holders = fn (string $permission) => Role::where('guard_name', 'users')->with('permissions')->get()
            ->filter(fn (Role $role) => $role->permissions->pluck('name')->contains($permission))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($holders('folios.post'), $holders('folios.dispute'));
        $this->assertSame(['reception'], $holders('folios.dispute'));
    }

    public function test_seeder_idempotent(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->assertCount(26, Permission::where('guard_name', 'users')->get());
        $this->assertCount(7, Role::where('guard_name', 'users')->get());

        // Idempotent down to the pivot: re-running must not double up grants.
        $this->assertCount(
            3,
            Role::where(['name' => 'content_editor', 'guard_name' => 'users'])->firstOrFail()->permissions,
        );
        $this->assertCount(
            4,
            Role::where(['name' => 'content_manager', 'guard_name' => 'users'])->firstOrFail()->permissions,
        );
    }
}
