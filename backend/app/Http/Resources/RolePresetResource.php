<?php
namespace App\Http\Resources;

use App\Base\BaseResource;

class RolePresetResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'name'        => $this->name,
            'permissions' => $this->permissions->pluck('name')->values(),
        ];
    }
}
