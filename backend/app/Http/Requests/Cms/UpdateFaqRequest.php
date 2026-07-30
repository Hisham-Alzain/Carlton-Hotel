<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class UpdateFaqRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'category'   => ['nullable', 'string', 'max:255'],
            ...TranslatableRules::sometimes('question', ['string', 'max:500']),
            ...TranslatableRules::sometimes('answer', ['string']),
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
