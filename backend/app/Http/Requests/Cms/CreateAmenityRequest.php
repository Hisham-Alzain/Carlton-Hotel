<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Support\Str;

class CreateAmenityRequest extends BaseRequest
{
    public function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name.en')) {
            $this->merge(['slug' => Str::slug($this->input('name.en'))]);
        }
    }

    public function rules(): array
    {
        return [
            'slug'       => ['required', 'string', 'max:255', 'unique:amenities,slug'],
            ...TranslatableRules::for('name', ['string', 'max:255']),
            'icon'       => ['nullable', 'string', 'max:64'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
