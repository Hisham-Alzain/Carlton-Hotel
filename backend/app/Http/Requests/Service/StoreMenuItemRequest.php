<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class StoreMenuItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'menu_category_uuid' => ['required', 'string', 'exists:menu_categories,uuid'],
            'name.en'            => ['required', 'string', 'max:255'],
            'name.ar'            => ['required', 'string', 'max:255'],
            'description.en'     => ['nullable', 'string'],
            'description.ar'     => ['nullable', 'string'],
            'price_usd'          => ['required', 'numeric', 'min:0'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }
}
