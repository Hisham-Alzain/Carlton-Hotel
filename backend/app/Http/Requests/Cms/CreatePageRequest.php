<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class CreatePageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slug'       => ['required', 'string', 'max:255', 'unique:pages,slug', 'regex:/^[a-z0-9-]+$/'],
            'title.en'   => ['required', 'string', 'max:255'],
            'title.ar'   => ['required', 'string', 'max:255'],
            'content.en' => ['required', 'string'],
            'content.ar' => ['required', 'string'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
