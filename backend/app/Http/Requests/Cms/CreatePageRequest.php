<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreatePageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['required', 'string', 'max:255', 'unique:pages,slug', 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::for('title', ['string', 'max:255']),
            ...TranslatableRules::for('content', ['string']),
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
