<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class StoreServiceItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'service_category_uuid' => ['required', 'string', 'exists:service_categories,uuid'],
            ...TranslatableRules::for('name', ['string', 'max:255']),
            ...TranslatableRules::optional('description', ['string']),
            'expected_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            // Null = complimentary; a value is charged to the folio.
            'price_usd'        => ['nullable', 'numeric', 'min:0'],
            'is_default'       => ['boolean'],
            'is_active'        => ['boolean'],
            'sort_order'       => ['integer', 'min:0'],
        ];
    }
}
