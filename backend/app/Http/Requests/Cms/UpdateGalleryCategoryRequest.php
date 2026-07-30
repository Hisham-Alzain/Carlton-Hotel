<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

class UpdateGalleryCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['sometimes', 'string', 'max:255', Rule::unique('gallery_categories', 'slug')->ignore($this->route('galleryCategory')), 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
