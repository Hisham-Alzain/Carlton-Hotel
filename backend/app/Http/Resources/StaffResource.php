<?php
namespace App\Http\Resources;

use App\Base\BaseResource;

class StaffResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'uuid'                 => $this->uuid(),
            'name'                 => $this->name,
            'email'                => $this->email,
            'type'                 => $this->type,
            'is_active'            => $this->is_active,
            'roles'                => $this->getRoleNames(),
            'effective_permissions'=> $this->getAllPermissions()->pluck('name')->values(),
            'direct_permissions'   => $this->getDirectPermissions()->pluck('name')->values(),
            'role_permissions'     => $this->getPermissionsViaRoles()->pluck('name')->values(),
        ];
    }
}
