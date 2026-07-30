<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;
use Illuminate\Support\Str;

class StoreMenuCategoryRequest extends BaseRequest
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
            'dining_venue_uuid' => ['required', 'string', 'exists:dining_venues,uuid'],
            'slug'              => ['required', 'string', 'max:64'],
            // en/ar required, fr/tr/es accepted and optional — `config/cms.php`
            // decides, not this file. Hardcoded `name.en` / `name.ar` keys were
            // why a French menu category was a 422 while a French room type was
            // fine.
            ...TranslatableRules::for('name', ['string', 'max:255']),
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
        ];
    }
}
