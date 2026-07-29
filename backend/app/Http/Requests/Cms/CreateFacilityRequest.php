<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateFacilityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::for('name', ['string', 'max:255']),
            ...TranslatableRules::for('description', ['string']),
            ...TranslatableRules::optional('location', ['string', 'max:255']),
            ...TranslatableRules::optional('hours', ['string', 'max:255']),
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
