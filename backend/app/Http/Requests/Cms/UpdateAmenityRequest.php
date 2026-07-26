<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateAmenityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['sometimes', 'string', 'max:255', Rule::unique('amenities', 'slug')->ignore($this->route('amenity')?->id)],
            'name.en'    => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar'    => ['sometimes', 'required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:64'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
