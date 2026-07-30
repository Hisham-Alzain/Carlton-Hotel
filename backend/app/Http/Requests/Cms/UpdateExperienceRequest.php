<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

class UpdateExperienceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // `ignore()` takes the resolved model, so re-saving a record without
            // changing its slug does not collide with itself.
            'slug'             => ['sometimes', 'string', 'max:255', Rule::unique('experiences', 'slug')->ignore($this->route('experience')), 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::sometimes('title', ['string', 'max:255']),
            ...TranslatableRules::sometimes('description', ['string']),
            'category'         => ['sometimes', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'price_usd'        => ['nullable', 'numeric', 'min:0'],
            'is_active'        => ['boolean'],
            'sort_order'       => ['integer', 'min:0'],
        ];
    }
}
