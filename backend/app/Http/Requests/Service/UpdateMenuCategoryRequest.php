<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

/**
 * A partial update of a menu category.
 *
 * The module used to point `update()` at `StoreMenuCategoryRequest`, so every
 * field was `required` on `PUT`: a client changing only `sort_order` had to
 * resend the venue, the slug and every translation, and one that did not got a
 * 422 — or, for the fields the model treats as nullable, wiped what it omitted.
 *
 * Two differences from the create request beyond `sometimes`:
 *
 * - `dining_venue_uuid` is absent. Moving a category to another restaurant is
 *   not an edit, it is a different category; the items hanging off it belong to
 *   the venue's menu. Re-parenting needs its own verb if it is ever wanted.
 * - There is no `prepareForValidation()` slug backfill. On create the slug is
 *   derived from the English name because there is nothing to keep stable yet.
 *   Deriving it again here would re-slug the category — and break the public
 *   `?type=<slug>` links the site already serves — every time an editor fixed a
 *   typo in the name. An explicit `slug` still changes it.
 */
class UpdateMenuCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['sometimes', 'string', 'max:64'],
            ...TranslatableRules::sometimes('name', ['string', 'max:255']),
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
