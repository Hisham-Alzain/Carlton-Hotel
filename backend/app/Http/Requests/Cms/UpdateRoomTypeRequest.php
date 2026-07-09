<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class UpdateRoomTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'        => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar'        => ['sometimes', 'required', 'string', 'max:255'],
            'description.en' => ['sometimes', 'required', 'string'],
            'description.ar' => ['sometimes', 'required', 'string'],
            'amenities'      => ['nullable', 'array'],
            'amenities.*'    => ['string', 'max:255'],
            'base_occupancy' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_occupancy'  => ['sometimes', 'integer', 'min:1', 'max:20'],
            'size_sqm'       => ['nullable', 'numeric', 'min:1'],
            'base_price_usd' => ['sometimes', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
