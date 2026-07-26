<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Enums\RoomStatus;
use Illuminate\Validation\Rule;

class CreateRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'number'       => ['required', 'string', 'max:10', 'unique:rooms,number'],
            'floor'        => ['nullable', 'integer', 'min:0', 'max:200'],
            'status'       => [Rule::enum(RoomStatus::class)],
            'is_active'    => ['boolean'],
        ];
    }
}
