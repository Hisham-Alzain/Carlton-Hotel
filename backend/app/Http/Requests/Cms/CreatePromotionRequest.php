<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreatePromotionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::for('title', ['string', 'max:255']),
            ...TranslatableRules::for('description', ['string']),
            ...TranslatableRules::optional('secondary_description', ['string']),
            ...TranslatableRules::optional('terms', ['string']),
            'valid_from'     => ['nullable', 'date'],
            'valid_until'    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
