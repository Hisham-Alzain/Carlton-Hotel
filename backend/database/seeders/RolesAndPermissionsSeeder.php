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
            // Phase 5 (D-05): folios.post (posting charges/credits) goes to every holder of folios.settle.
            // folios.dispute (staff dispute raise/resolve/reject) goes to the same presets (D-11).
            'folios.view', 'folios.settle', 'folios.post', 'folios.dispute',
            // `cms.restore` and `cms.purge` are split off `cms.edit` because
            // neither is an edit. Restoring is undo, and belongs with editing —
            // an editor who can delete must be able to take it back. Purging
            // leaves the recycle bin empty and the row gone, along with its
            // cascade children and, through `PurgesMedia`, their files: an act
            // no other endpoint can reverse, which is why it is not something a
            // junior content editor holds by default.
            'cms.view', 'cms.edit', 'cms.restore', 'cms.purge',
            // Opens the `rooms` group: moving a room through the housekeeping
            // lifecycle. Deliberately independent of `cms.edit` — housekeeping
            // must not need CMS rights to mark a room clean.
            'rooms.status',
            'service_requests.view', 'service_requests.assign', 'service_requests.update',
            'tickets.view', 'tickets.assign', 'tickets.respond',
            'pricing.edit', 'reports.view', 'staff.manage',
            // Phase 4 (D-01): staff guest directory, profile, notes and preferences.
            'guests.view', 'guests.edit',
            // Phase 6 (D-06): the housekeeping task board. view = list/show,
            // assign = create and assign, update = move a task through its statuses.
            'housekeeping.view', 'housekeeping.assign', 'housekeeping.update',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'users']);
        }

        // Phase 7 (D-10): no new permission strings. Reception gains
        // tickets.view + tickets.respond and concierge gains tickets.view +
        // tickets.assign + tickets.respond. tickets.* is the guest-relations
        // set, so these roles also gain guest chat read and reply
        // (/cms/conversations) and event-inquiry read — concierge also its
        // status/assign (council A4 / PR-6; PROJECT.md debt entry, split into
        // support_tickets.* on objection). Kitchen and housekeeping deliberately
        // stay without tickets.*.
        $presets = [
            'reception'      => ['reservations.view', 'reservations.create', 'reservations.cancel', 'folios.view', 'folios.settle', 'folios.post', 'folios.dispute', 'service_requests.view', 'service_requests.update', 'rooms.status', 'guests.view', 'guests.edit', 'housekeeping.view', 'housekeeping.assign', 'tickets.view', 'tickets.respond'],
            'kitchen'        => ['service_requests.view', 'service_requests.update'],
            'housekeeping'   => ['service_requests.view', 'service_requests.update', 'rooms.status', 'housekeeping.view', 'housekeeping.assign', 'housekeeping.update'],
            'concierge'      => ['service_requests.view', 'service_requests.assign', 'service_requests.update', 'guests.view', 'guests.edit', 'tickets.view', 'tickets.assign', 'tickets.respond'],
            'events'         => ['service_requests.view', 'tickets.view', 'tickets.assign', 'tickets.respond'],
            // Without this preset no seeded account except the super admin (who
            // passes via Gate::before, not via permission rows) can reach
            // /api/cms/* — the CMS shipped ungrantable.
            //
            // `cms.restore` is here and `cms.purge` is not: the editor who made
            // the delete is the one who wants it back, but emptying the bin
            // takes the row, its cascade children and their image files with no
            // way back, which is a senior decision.
            'content_editor'  => ['cms.view', 'cms.edit', 'cms.restore'],
            // The senior editorial preset — everything `content_editor` holds,
            // plus the authority to empty the recycle bin. Without it `cms.purge`
            // would be grantable only per account and the CMS would ship with a
            // bin nothing can empty, which is the state this whole change exists
            // to end.
            'content_manager' => ['cms.view', 'cms.edit', 'cms.restore', 'cms.purge'],
        ];

        foreach ($presets as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'users']);
            $role->syncPermissions($rolePerms);
        }
    }
}
