<?php
namespace App\Http\Resources;

use App\Base\BaseResource;

class UserResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'uuid'        => $this->uuid,
            'name'        => $this->name,
            'email'       => $this->email,
            'type'        => $this->type,
            'is_active'   => $this->is_active,
            'roles'       => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
