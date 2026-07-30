<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

/**
 * A partial update of a microservice inside a guest-service category.
 *
 * `price_usd` keeps `nullable` alongside `sometimes`, and the pairing is
 * deliberate: null is not "unset" here, it is *complimentary* (see
 * `StoreServiceItemRequest`). An explicit null must therefore be able to make a
 * charged item free, while omitting the key leaves the price where it is —
 * exactly the distinction reusing the create request could not express, because
 * there every omitted field was a 422 or a blanking.
 */
class UpdateServiceItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'service_category_uuid' => ['sometimes', 'string', 'exists:service_categories,uuid'],
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            ...TranslatableRules::optional('description', ['string']),
            'expected_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10080'],
            'price_usd'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_default'       => ['sometimes', 'boolean'],
            'is_active'        => ['sometimes', 'boolean'],
            'sort_order'       => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
