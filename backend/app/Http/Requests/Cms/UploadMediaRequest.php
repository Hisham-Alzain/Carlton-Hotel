<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;

class UploadMediaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'image'      => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
