<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateGalleryCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['required', 'string', 'max:255', 'unique:gallery_categories,slug', 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::for('name', ['string', 'max:255']),
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
