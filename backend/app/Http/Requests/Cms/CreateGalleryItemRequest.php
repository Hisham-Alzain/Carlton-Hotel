<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class CreateGalleryItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // The chip is addressed by uuid, never by the autoincrement id — the
            // service resolves it (see GalleryItemService::resolveCategory()).
            'gallery_category_uuid' => ['required', 'string', 'exists:gallery_categories,uuid'],
            ...TranslatableRules::for('caption', ['string', 'max:500']),
            'is_active'             => ['boolean'],
            'sort_order'            => ['integer', 'min:0'],
        ];
    }
}
