<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;

class AssignRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_uuid' => ['required', 'string', 'exists:rooms,uuid'],
        ];
    }
}
