<?php
namespace App\Http\Resources;

use App\Base\BaseResource;

class UserResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'uuid'           => $this->uuid,
            'name'           => $this->name,
            'email'          => $this->email,
            'type'           => $this->type,
            'is_active'      => $this->is_active,
            'is_super_admin' => $this->resource->isSuperAdmin(),
            'roles'          => $this->getRoleNames(),
            // Effective, not assigned: a super admin passes every gate through
            // Gate::before while holding zero permission rows, so the raw
            // Spatie list would tell clients "no access" for the one account
            // that has full access. See User::effectivePermissionNames().
            'permissions'    => $this->resource->effectivePermissionNames(),
        ];
    }
}
