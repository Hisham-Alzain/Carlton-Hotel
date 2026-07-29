<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateHomeSliderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::for('header_text', ['string', 'max:255']),
            ...TranslatableRules::for('location', ['string', 'max:255']),
            ...TranslatableRules::for('description_text', ['string', 'max:1000']),
            'is_active'           => ['boolean'],
            'sort_order'          => ['integer', 'min:0'],
        ];
    }
}
