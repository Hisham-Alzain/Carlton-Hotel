<?php

namespace App\Http\Requests\Housekeeping;

use App\Base\BaseRequest;
use App\Enums\HousekeepingTaskType;
use App\Enums\ServiceRequestPriority;
use Illuminate\Validation\Rule;

/** POST /housekeeping/tasks (D-08): staff create turnover, stayover or inspection tasks; request tasks are system-made. */
class CreateHousekeepingTaskRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_uuid' => ['required', 'string', 'exists:rooms,uuid'],
            'type'      => ['required', 'string', Rule::in(array_map(
                static fn (HousekeepingTaskType $t) => $t->value,
                HousekeepingTaskType::deduped(),
            ))],
            'due_at'    => ['nullable', 'date', 'after_or_equal:now'],
            'priority'  => ['nullable', Rule::enum(ServiceRequestPriority::class)],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
