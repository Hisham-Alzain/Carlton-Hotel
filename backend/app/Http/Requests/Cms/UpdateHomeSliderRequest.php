<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class UpdateHomeSliderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'header_text.en'      => ['sometimes', 'required', 'string', 'max:255'],
            'header_text.ar'      => ['sometimes', 'required', 'string', 'max:255'],
            'location.en'         => ['sometimes', 'required', 'string', 'max:255'],
            'location.ar'         => ['sometimes', 'required', 'string', 'max:255'],
            'description_text.en' => ['sometimes', 'required', 'string', 'max:1000'],
            'description_text.ar' => ['sometimes', 'required', 'string', 'max:1000'],
            'is_active'           => ['boolean'],
            'sort_order'          => ['integer', 'min:0'],
        ];
    }
}
