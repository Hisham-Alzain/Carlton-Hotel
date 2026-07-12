<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class CreateServiceBookingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'bookable_type' => ['required', 'string', 'in:spa_service,restaurant_table,pool_cabana,transfer'],
            'bookable_uuid' => ['required', 'string'],
            'scheduled_at'  => ['required', 'date', 'after:now'],
            'notes'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
