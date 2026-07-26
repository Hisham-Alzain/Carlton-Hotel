<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class SettleFolioRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'method'     => ['required', Rule::enum(PaymentMethod::class)],
            'amount_usd' => ['required', 'numeric', 'min:0.01'],
            'note'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}
