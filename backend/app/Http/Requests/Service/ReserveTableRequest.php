<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class ReserveTableRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time'            => ['required', 'date_format:H:i'],
            'guest_count'     => ['required', 'integer', 'min:1', 'max:20'],
            'special_request' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
