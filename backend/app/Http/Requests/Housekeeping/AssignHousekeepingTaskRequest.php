<?php

namespace App\Http\Requests\Housekeeping;

use App\Base\BaseRequest;

/** PATCH /housekeeping/tasks/{task}/assign (D-05). */
class AssignHousekeepingTaskRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'exists:users,uuid'],
        ];
    }
}
