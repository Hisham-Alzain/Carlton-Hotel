<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\HotelClock;

class ReserveTableRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // `date` + `time` are hotel-local, so "today" is the hotel's day (Phase 8, D-22).
            'date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:' . HotelClock::today()->toDateString()],
            'time'            => ['required', 'date_format:H:i'],
            'guest_count'     => ['required', 'integer', 'min:1', 'max:20'],
            'special_request' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
