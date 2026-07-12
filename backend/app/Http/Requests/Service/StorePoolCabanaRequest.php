<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class StorePoolCabanaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'   => ['required', 'string', 'max:255'],
            'name.ar'   => ['required', 'string', 'max:255'],
            'capacity'  => ['required', 'integer', 'min:1'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
