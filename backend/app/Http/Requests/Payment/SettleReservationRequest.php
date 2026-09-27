<?php

namespace App\Http\Requests\Payment;

use App\Base\BaseRequest;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class SettleReservationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'method'     => ['required', Rule::enum(PaymentMethod::class)],
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'note'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}
