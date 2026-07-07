<?php
namespace App\Http\Resources;

use App\Base\BaseResource;

class PermissionResource extends BaseResource
{
    // Wraps one permission GROUP (module + its permissions), not one permission.
    public function toArray($request): array
    {
        return [
            'module'      => $this->resource['module'],
            'permissions' => $this->resource['permissions'],
        ];
    }
}
