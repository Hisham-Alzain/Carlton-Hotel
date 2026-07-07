<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_16_permissions_seeded(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $expected = [
            'reservations.view', 'reservations.create', 'reservations.cancel',
            'folios.view', 'folios.settle', 'cms.view', 'cms.edit',
            'service_requests.view', 'service_requests.assign', 'service_requests.update',
            'tickets.view', 'tickets.assign', 'tickets.respond',
            'pricing.edit', 'reports.view', 'staff.manage',
        ];
        foreach ($expected as $p) {
            $this->assertDatabaseHas('permissions', ['name' => $p, 'guard_name' => 'users']);
        }
        $this->assertCount(16, Permission::where('guard_name', 'users')->get());
    }

    public function test_all_5_role_presets_seeded(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        foreach (['reception', 'kitchen', 'housekeeping', 'concierge', 'events'] as $r) {
            $this->assertDatabaseHas('roles', ['name' => $r, 'guard_name' => 'users']);
        }
        $this->assertCount(5, Role::where('guard_name', 'users')->get());
    }

    public function test_seeder_idempotent(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->assertCount(16, Permission::where('guard_name', 'users')->get());
        $this->assertCount(5, Role::where('guard_name', 'users')->get());
    }
}
