<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreateRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'number'       => ['required', 'string', 'max:10', 'unique:rooms,number'],
            'floor'        => ['nullable', 'integer', 'min:0', 'max:200'],
            'status'       => ['in:available,occupied,maintenance'],
            'is_active'    => ['boolean'],
        ];
    }
}
