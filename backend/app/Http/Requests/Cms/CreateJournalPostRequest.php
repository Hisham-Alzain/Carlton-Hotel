<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateJournalPostRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'         => ['required', 'string', 'max:255', 'unique:journal_posts,slug', 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::for('title', ['string', 'max:255']),
            ...TranslatableRules::optional('excerpt', ['string', 'max:1000']),
            ...TranslatableRules::for('body', ['string']),
            ...TranslatableRules::optional('category', ['string', 'max:100']),
            // Required, and deliberately NOT bounded by `before_or_equal:today`:
            // a future date is a legitimate editorial choice (a dated preview, a
            // launch note), and the public endpoints show it either way.
            'published_on' => ['required', 'date'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['integer', 'min:0'],
        ];
    }
}
