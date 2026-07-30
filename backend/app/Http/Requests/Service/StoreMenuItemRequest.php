<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class StoreMenuItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'menu_category_uuid' => ['required', 'string', 'exists:menu_categories,uuid'],
            ...TranslatableRules::for('name', ['string', 'max:255']),
            ...TranslatableRules::optional('description', ['string']),
            'price_usd'          => ['required', 'numeric', 'min:0'],
            'is_vegan'           => ['nullable', 'boolean'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }
}
