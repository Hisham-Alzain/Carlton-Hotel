<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class UpdateFacilityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'        => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar'        => ['sometimes', 'required', 'string', 'max:255'],
            'description.en' => ['sometimes', 'required', 'string'],
            'description.ar' => ['sometimes', 'required', 'string'],
            'location.en'    => ['nullable', 'string', 'max:255'],
            'location.ar'    => ['nullable', 'string', 'max:255'],
            'hours.en'       => ['nullable', 'string', 'max:255'],
            'hours.ar'       => ['nullable', 'string', 'max:255'],
            'is_active'      => ['boolean'],
            'sort_order'     => ['integer', 'min:0'],
        ];
    }
}
