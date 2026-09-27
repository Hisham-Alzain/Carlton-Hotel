<?php

namespace App\Http\Requests\Housekeeping;

use App\Base\BaseRequest;
use App\Enums\HousekeepingTaskStatus;
use Illuminate\Validation\Rule;

/** PATCH /housekeeping/tasks/{task}/status (D-05); the transition itself is checked by the status writer. */
class UpdateHousekeepingTaskStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(HousekeepingTaskStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
