<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class RequestTransportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
