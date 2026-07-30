<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

class UpdateJournalPostRequest extends BaseRequest
{
    public function rules(): array
    {
        $post = $this->route('journalPost');

        return [
            'slug'         => ['sometimes', 'string', 'max:255', Rule::unique('journal_posts', 'slug')->ignore($post), 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::sometimes('title', ['string', 'max:255']),
            ...TranslatableRules::optional('excerpt', ['string', 'max:1000']),
            ...TranslatableRules::sometimes('body', ['string']),
            ...TranslatableRules::optional('category', ['string', 'max:100']),
            'published_on' => ['sometimes', 'date'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['integer', 'min:0'],
        ];
    }
}
