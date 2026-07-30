<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateTestimonialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:255'],
            ...TranslatableRules::optional('author_title', ['string', 'max:255']),
            ...TranslatableRules::for('quote', ['string']),
            'rating'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['integer', 'min:0'],
        ];
    }
}
