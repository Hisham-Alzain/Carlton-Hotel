<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreateDiningVenueRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'          => ['required', 'string', 'max:255'],
            'name.ar'          => ['required', 'string', 'max:255'],
            'description.en'   => ['required', 'string'],
            'description.ar'   => ['required', 'string'],
            'cuisine_type.en'  => ['nullable', 'string', 'max:255'],
            'cuisine_type.ar'  => ['nullable', 'string', 'max:255'],
            'location.en'      => ['nullable', 'string', 'max:255'],
            'location.ar'      => ['nullable', 'string', 'max:255'],
            'hours.en'         => ['nullable', 'string', 'max:255'],
            'hours.ar'         => ['nullable', 'string', 'max:255'],
            'is_active'        => ['boolean'],
            'sort_order'       => ['integer', 'min:0'],
        ];
    }
}
