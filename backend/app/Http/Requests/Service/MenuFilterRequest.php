<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class MenuFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Menu category slug — omit for the full menu.
            'type' => ['nullable', 'string', 'max:64'],
        ];
    }
}
