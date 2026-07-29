<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class UpdateEventSpaceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            ...TranslatableRules::sometimes('description', ['string']),
            'capacity'       => ['nullable', 'integer', 'min:1'],
            ...TranslatableRules::optional('location', ['string', 'max:255']),
            ...TranslatableRules::optional('amenities', ['string']),
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
