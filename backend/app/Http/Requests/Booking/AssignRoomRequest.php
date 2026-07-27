<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;

class AssignRoomRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Optional: rooms are reserved at booking time, so omitting this
            // checks the guest into the room they already hold. Send it only to
            // move them to a different room of the same type.
            'room_uuid' => ['nullable', 'string', 'exists:rooms,uuid'],
        ];
    }
}
