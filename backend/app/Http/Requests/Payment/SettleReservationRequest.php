<?php

namespace App\Http\Requests\Payment;

use App\Base\BaseRequest;

class SettleReservationRequest extends BaseRequest
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
