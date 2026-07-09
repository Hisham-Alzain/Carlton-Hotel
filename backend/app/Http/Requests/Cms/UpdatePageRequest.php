<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends BaseRequest
{
    public function rules(): array
    {
        $page = $this->route('page');
        return [
            'slug'       => ['sometimes', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page), 'regex:/^[a-z0-9-]+$/'],
            'title.en'   => ['sometimes', 'required', 'string', 'max:255'],
            'title.ar'   => ['sometimes', 'required', 'string', 'max:255'],
            'content.en' => ['sometimes', 'required', 'string'],
            'content.ar' => ['sometimes', 'required', 'string'],
            'is_active'  => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
