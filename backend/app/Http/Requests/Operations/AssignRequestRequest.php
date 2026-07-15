<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;

class AssignRequestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'exists:users,uuid'],
        ];
    }
}
