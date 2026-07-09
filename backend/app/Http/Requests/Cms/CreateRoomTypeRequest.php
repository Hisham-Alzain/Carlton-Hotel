<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreateRoomTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'        => ['required', 'string', 'max:255'],
            'name.ar'        => ['required', 'string', 'max:255'],
            'description.en' => ['required', 'string'],
            'description.ar' => ['required', 'string'],
            'amenities'      => ['nullable', 'array'],
            'amenities.*'    => ['string', 'max:255'],
            'base_occupancy' => ['required', 'integer', 'min:1', 'max:20'],
            'max_occupancy'  => ['required', 'integer', 'min:1', 'max:20', 'gte:base_occupancy'],
            'size_sqm'       => ['nullable', 'numeric', 'min:1'],
            'base_price_usd' => ['required', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
