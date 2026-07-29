<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class UpdateHomeSliderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::sometimes('header_text', ['string', 'max:255']),
            ...TranslatableRules::sometimes('location', ['string', 'max:255']),
            ...TranslatableRules::sometimes('description_text', ['string', 'max:1000']),
            'is_active'           => ['boolean'],
            'sort_order'          => ['integer', 'min:0'],
        ];
    }
}
