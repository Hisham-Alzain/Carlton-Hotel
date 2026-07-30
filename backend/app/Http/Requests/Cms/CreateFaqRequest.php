<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateFaqRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'category'   => ['nullable', 'string', 'max:255'],
            ...TranslatableRules::for('question', ['string', 'max:500']),
            ...TranslatableRules::for('answer', ['string']),
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
