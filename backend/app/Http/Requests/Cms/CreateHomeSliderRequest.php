<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreateHomeSliderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'header_text.en'      => ['required', 'string', 'max:255'],
            'header_text.ar'      => ['required', 'string', 'max:255'],
            'location.en'         => ['required', 'string', 'max:255'],
            'location.ar'         => ['required', 'string', 'max:255'],
            'description_text.en' => ['required', 'string', 'max:1000'],
            'description_text.ar' => ['required', 'string', 'max:1000'],
            'is_active'           => ['boolean'],
            'sort_order'          => ['integer', 'min:0'],
        ];
    }
}
