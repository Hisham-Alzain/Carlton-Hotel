<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'reservations.view', 'reservations.create', 'reservations.cancel',
            'folios.view', 'folios.settle',
            'cms.view', 'cms.edit',
            'service_requests.view', 'service_requests.assign', 'service_requests.update',
            'tickets.view', 'tickets.assign', 'tickets.respond',
            'pricing.edit', 'reports.view', 'staff.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'users']);
        }

        $presets = [
            'reception'      => ['reservations.view', 'reservations.create', 'reservations.cancel', 'folios.view', 'folios.settle', 'service_requests.view'],
            'kitchen'        => ['service_requests.view', 'service_requests.update'],
            'housekeeping'   => ['service_requests.view', 'service_requests.update'],
            'concierge'      => ['service_requests.view', 'service_requests.assign', 'service_requests.update'],
            'events'         => ['service_requests.view', 'tickets.view', 'tickets.assign', 'tickets.respond'],
            // Without this preset no seeded account except the super admin (who
            // passes via Gate::before, not via permission rows) can reach
            // /api/cms/* — the CMS shipped ungrantable.
            'content_editor' => ['cms.view', 'cms.edit'],
        ];

        foreach ($presets as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'users']);
            $role->syncPermissions($rolePerms);
        }
    }
}
