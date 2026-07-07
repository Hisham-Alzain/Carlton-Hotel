<?php
namespace App\Policies;

use App\Models\User;

class StaffPolicy
{
    // Gate::before in AppServiceProvider already returns true for super_admin.
    // All methods below run for non-super-admins only.

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('staff.manage');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('staff.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('staff.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('staff.manage') && ! $target->isSuperAdmin();
    }

    public function assignPermissions(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('staff.manage') && ! $target->isSuperAdmin();
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('staff.manage')
            && ! $target->isSuperAdmin()
            && $actor->id !== $target->id;
    }
}
