<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

/**
 * A partial update of a dish.
 *
 * `menu_category_uuid` stays accepted here — unlike a category's venue, moving a
 * dish between courses (starter → main) is an ordinary editorial correction, and
 * both categories belong to the same menu. It is `sometimes` rather than
 * `required`: omitting it leaves the dish where it is.
 *
 * `description` is `optional()` (nullable in every locale) so an explicit null
 * still clears a translation, while omitting the key leaves it alone — the point
 * of a partial update. `name` is `sometimes()`: it may be omitted, but the
 * required locales can never be blanked.
 */
class UpdateMenuItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'menu_category_uuid' => ['sometimes', 'string', 'exists:menu_categories,uuid'],
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            ...TranslatableRules::optional('description', ['string']),
            'price_usd'          => ['sometimes', 'numeric', 'min:0'],
            'is_vegan'           => ['sometimes', 'boolean'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }
}
