<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

/**
 * POST /cms/folios/{folio}/payments (D-13). The `Idempotency-Key` header is
 * REQUIRED here: it is merged in as `idempotency_key` (null when absent, blank
 * or whitespace, always overwriting a body field) and a missing key fails
 * validation with `errors.idempotency_key` = custom.errors.idempotency_key_required
 * (D-08, consultant ruling plan-q3).
 */
class RecordFolioPaymentRequest extends BaseRequest
{
    public function prepareForValidation(): void
    {
        $key = trim((string) $this->header('Idempotency-Key', ''));

        $this->merge(['idempotency_key' => $key === '' ? null : $key]);
    }

    public function rules(): array
    {
        return [
            'method'          => ['required', Rule::enum(PaymentMethod::class)],
            'amount_usd'      => ['required', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'note'            => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'idempotency_key.required' => __('custom.errors.idempotency_key_required'),
        ]);
    }
}
