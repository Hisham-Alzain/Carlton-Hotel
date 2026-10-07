<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class StoreTransferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'   => ['required', 'string', 'max:255'],
            'name.ar'   => ['required', 'string', 'max:255'],
            'description'    => ['sometimes', 'array'],
            'description.en' => ['nullable', 'string', 'max:2000'],
            'description.ar' => ['nullable', 'string', 'max:2000'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'max_passengers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
