<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreateEventSpaceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'        => ['required', 'string', 'max:255'],
            'name.ar'        => ['required', 'string', 'max:255'],
            'description.en' => ['required', 'string'],
            'description.ar' => ['required', 'string'],
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
