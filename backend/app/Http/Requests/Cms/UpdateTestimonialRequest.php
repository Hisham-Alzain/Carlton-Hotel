<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class UpdateTestimonialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'author_name' => ['sometimes', 'string', 'max:255'],
            ...TranslatableRules::optional('author_title', ['string', 'max:255']),
            ...TranslatableRules::sometimes('quote', ['string']),
            'rating'      => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['integer', 'min:0'],
        ];
    }
}
