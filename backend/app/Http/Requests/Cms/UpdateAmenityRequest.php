<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Validation\Rule;

class UpdateAmenityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['sometimes', 'string', 'max:255', Rule::unique('amenities', 'slug')->ignore($this->route('amenity')?->id)],
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            'icon'       => ['nullable', 'string', 'max:64'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
