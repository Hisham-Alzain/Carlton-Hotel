<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class StoreRestaurantTableRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'dining_venue_uuid' => ['nullable', 'string', 'exists:dining_venues,uuid'],
            'table_number'      => ['required', 'string', 'max:50'],
            'capacity'          => ['required', 'integer', 'min:1'],
            'is_active'         => ['nullable', 'boolean'],
        ];
    }
}
