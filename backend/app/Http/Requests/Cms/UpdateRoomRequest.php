<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        $room = $this->route('room');
        return [
            'room_type_uuid' => ['sometimes', 'string', 'exists:room_types,uuid'],
            'number'       => ['sometimes', 'string', 'max:10', Rule::unique('rooms', 'number')->ignore($room)],
            'floor'        => ['nullable', 'integer', 'min:0', 'max:200'],
            'status'       => ['in:available,occupied,maintenance'],
            'is_active'    => ['boolean'],
        ];
    }
}
