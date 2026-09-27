<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

/**
 * POST /cms/folios/{folio}/settle. Phase 5 (D-14): the amount is optional so a
 * folio with nothing due can close with `{}`; when an amount is sent it needs a
 * method. Whether money is due is decided under the folio lock in
 * SettleFolioAction, not here.
 */
class SettleFolioRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'method'     => ['required_with:amount_usd', 'nullable', Rule::enum(PaymentMethod::class)],
            'amount_usd' => ['nullable', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'note'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}
