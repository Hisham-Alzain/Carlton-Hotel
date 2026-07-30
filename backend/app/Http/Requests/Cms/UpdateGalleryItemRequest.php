<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

class UpdateGalleryItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'gallery_category_uuid' => ['sometimes', 'string', 'exists:gallery_categories,uuid'],
            ...TranslatableRules::sometimes('caption', ['string', 'max:500']),
            'is_active'             => ['boolean'],
            'sort_order'            => ['integer', 'min:0'],
        ];
    }
}
