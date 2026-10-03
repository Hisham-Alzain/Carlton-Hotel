<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;
use App\Http\Requests\Concerns\ReadsIdempotencyKey;

/**
 * PATCH /cms/event-inquiries/{inquiry}/deposit (Phase 8, D-16). The
 * `Idempotency-Key` header is required. `method` is cash only: `on_arrival`
 * means nothing for a pre-event deposit, and new methods would widen folio
 * validation too (deferred). No check against `budget_usd` (indicative only).
 */
class RecordEventDepositRequest extends BaseRequest
{
    use ReadsIdempotencyKey;

    public function rules(): array
    {
        return [
            'amount_usd'      => ['required', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'method'          => ['sometimes', 'string', 'in:cash'],
            'note'            => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), $this->idempotencyKeyMessages());
    }
}
