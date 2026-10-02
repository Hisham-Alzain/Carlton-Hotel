<?php

namespace App\Http\Resources\Operations;

use App\Base\BaseResource;
use App\Enums\Department;
use Illuminate\Http\Request;

/**
 * One row of the assignable-staff directory (Phase 7, D-23, council A5):
 * exactly uuid, name, type and departments[] — no email, roles or
 * permissions. `departments` are the loaded role names that are Department
 * values, always an array.
 */
class OperationsStaffResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $departments = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->filter(fn (string $name) => Department::tryFrom($name) !== null)->values()->all()
            : [];

        return [
            'uuid'        => $this->uuid,
            'name'        => $this->name,
            'type'        => $this->type,
            'departments' => $departments,
        ];
    }
}
