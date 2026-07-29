<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends BaseRequest
{
    public function rules(): array
    {
        $page = $this->route('page');
        return [
            'slug'       => ['sometimes', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page), 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::sometimes('title', ['string', 'max:255']),
            ...TranslatableRules::sometimes('content', ['string']),
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
