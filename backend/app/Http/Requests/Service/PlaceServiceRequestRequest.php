<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Enums\ServiceRequestPriority;
use Illuminate\Validation\Rule;

class PlaceServiceRequestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type'     => ['required', 'string', 'max:255'],
            'priority' => ['nullable', Rule::enum(ServiceRequestPriority::class)],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
