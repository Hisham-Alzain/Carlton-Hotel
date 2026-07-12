<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class PlaceServiceRequestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type'     => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'in:low,normal,high'],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
