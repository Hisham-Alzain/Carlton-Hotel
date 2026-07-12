<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class ApproveCheckInRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:approved,rejected'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
