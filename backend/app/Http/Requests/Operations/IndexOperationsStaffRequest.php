<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Enums\Department;
use App\Support\OperationsQueueType;
use Illuminate\Validation\Rule;

/**
 * `GET /operations/staff` query (Phase 7, D-23, council A5).
 *
 * `type` is a queue registry segment; `permission` accepts only the three
 * registry work permissions (A5 — never a lookup against the full permission
 * table); `department` is a Department value; `search` is a name fragment.
 */
class IndexOperationsStaffRequest extends BaseRequest
{
    public function rules(): array
    {
        $types = collect(OperationsQueueType::all());

        return [
            'type'       => ['nullable', 'string', Rule::in($types->pluck('segment')->all())],
            'permission' => ['nullable', 'string', Rule::in($types->pluck('statusPermission')->unique()->values()->all())],
            'department' => ['nullable', Rule::enum(Department::class)],
            'search'     => ['nullable', 'string', 'max:100'],
        ];
    }
}
