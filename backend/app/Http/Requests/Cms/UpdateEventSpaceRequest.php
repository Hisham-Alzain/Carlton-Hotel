<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class UpdateEventSpaceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'        => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar'        => ['sometimes', 'required', 'string', 'max:255'],
            'description.en' => ['sometimes', 'required', 'string'],
            'description.ar' => ['sometimes', 'required', 'string'],
            'capacity'       => ['nullable', 'integer', 'min:1'],
            'location.en'    => ['nullable', 'string', 'max:255'],
            'location.ar'    => ['nullable', 'string', 'max:255'],
            'amenities.en'   => ['nullable', 'string'],
            'amenities.ar'   => ['nullable', 'string'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
