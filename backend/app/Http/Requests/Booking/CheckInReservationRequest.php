<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class CheckInReservationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Optional: omit to use the room reserved at booking time, or the
            // first free room of the type when none is reserved (D-01).
            'room_uuid'      => ['nullable', 'string', 'exists:rooms,uuid'],
            // Allows check-in on the day before arrival only, with a reason (D-02).
            'early_check_in' => ['sometimes', 'boolean'],
            'reason'         => ['nullable', Rule::requiredIf(fn () => $this->boolean('early_check_in')), 'string', 'max:255'],
        ];
    }
}
