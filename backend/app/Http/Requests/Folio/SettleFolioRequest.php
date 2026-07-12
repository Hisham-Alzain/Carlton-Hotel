<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;

class SettleFolioRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'method'     => ['required', 'string', 'in:cash,on_arrival'],
            'amount_usd' => ['required', 'numeric', 'min:0.01'],
            'note'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}
