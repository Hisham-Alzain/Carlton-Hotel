<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionAssignmentService
{
    // P2: "revoke" removes only direct grants. Revoking a role-inherited permission
    // is not supported (no native negative-permission in spatie). Deferred to future phase.

    public function apply(User $target, array $grant, array $revoke): array
    {
        DB::transaction(function () use ($target, $grant, $revoke) {
            foreach ($grant as $name) {
                $target->givePermissionTo($name);
            }
            foreach ($revoke as $name) {
                $target->revokePermissionTo($name);
            }
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $target->load('roles', 'permissions');
        return ['data' => $target, 'code' => 200];
    }

    public function heldBy(User $actor): Collection
    {
        return $actor->getAllPermissions()->pluck('name');
    }

    public function groupedPermissions(): array
    {
        // Bounded reference list (16 items) — get() acceptable, not paginated
        $grouped = Permission::where('guard_name', 'users')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name, 2)[0])
            ->map(fn ($perms, $module) => [
                'module'      => $module,
                'permissions' => $perms->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();

        return ['data' => $grouped, 'code' => 200];
    }
}
