<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_18_permissions_seeded(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $expected = [
            'reservations.view', 'reservations.create', 'reservations.cancel',
            'folios.view', 'folios.settle',
            // cms.restore and cms.purge are the recycle bin, split off cms.edit
            // because undoing a delete and destroying a record permanently are
            // not edits — see RolesAndPermissionsSeeder and RecycleBinTest.
            'cms.view', 'cms.edit', 'cms.restore', 'cms.purge',
            'service_requests.view', 'service_requests.assign', 'service_requests.update',
            'tickets.view', 'tickets.assign', 'tickets.respond',
            'pricing.edit', 'reports.view', 'staff.manage',
        ];
        foreach ($expected as $p) {
            $this->assertDatabaseHas('permissions', ['name' => $p, 'guard_name' => 'users']);
        }
        $this->assertCount(18, Permission::where('guard_name', 'users')->get());
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

    public function test_seeder_idempotent(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->assertCount(18, Permission::where('guard_name', 'users')->get());
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
