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
            'price_usd' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
