<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class UpdatePromotionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title.en'       => ['sometimes', 'required', 'string', 'max:255'],
            'title.ar'       => ['sometimes', 'required', 'string', 'max:255'],
            'description.en' => ['sometimes', 'required', 'string'],
            'description.ar' => ['sometimes', 'required', 'string'],
            'terms.en'       => ['nullable', 'string'],
            'terms.ar'       => ['nullable', 'string'],
            'valid_from'     => ['nullable', 'date'],
            'valid_until'    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
