<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateExperienceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'             => ['required', 'string', 'max:255', 'unique:experiences,slug', 'regex:/^[a-z0-9-]+$/'],
            ...TranslatableRules::for('title', ['string', 'max:255']),
            ...TranslatableRules::for('description', ['string']),
            // A stable lowercase key, not a display label — see the migration.
            'category'         => ['required', 'string', 'max:255'],
            // Published copy, optional in every locale: "2–6 guests" /
            // "2–6 ضيوف" / "2–6 convives". Not a number — see the migration.
            ...TranslatableRules::optional('group_size', ['string', 'max:255']),
            // unsignedSmallInteger ceiling; 1440 keeps it inside a single day.
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            // The range the site prints ("2–3 hours", "Half day"), beside the
            // upper bound above that a concierge actually blocks out.
            ...TranslatableRules::optional('duration_label', ['string', 'max:255']),
            'price_usd'        => ['nullable', 'numeric', 'min:0'],
            'is_active'        => ['boolean'],
            'sort_order'       => ['integer', 'min:0'],
        ];
    }
}
