<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;

class CheckAvailabilityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'room_type_uuid' => ['required', 'string', 'exists:room_types,uuid'],
            'check_in'       => ['required', 'date', 'after_or_equal:today'],
            'check_out'      => ['required', 'date', 'after:check_in'],
        ];
    }
}
