<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Enums\BookableType;
use Illuminate\Validation\Rule;

class CreateServiceBookingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'bookable_type' => ['required', Rule::enum(BookableType::class)],
            'bookable_uuid' => ['required', 'string'],
            'scheduled_at'  => ['required', 'date', 'after:now'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
