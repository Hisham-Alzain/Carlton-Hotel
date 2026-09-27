<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * POST /cms/folios/{folio}/line-items (D-05). A charge by default; a credit
 * needs a reason and may name the charge it reverses on this same folio.
 *
 * The optional `Idempotency-Key` header is merged in as `idempotency_key`
 * (null when absent, blank or whitespace), so a body field of that name can
 * never stand in for the header (D-08, consultant ruling plan-q3).
 */
class PostFolioItemRequest extends BaseRequest
{
    public function prepareForValidation(): void
    {
        $key = trim((string) $this->header('Idempotency-Key', ''));

        $this->merge(['idempotency_key' => $key === '' ? null : $key]);
        $this->mergeIfMissing(['kind' => 'charge', 'quantity' => 1]);
    }

    public function rules(): array
    {
        return [
            'kind'               => ['required', 'in:charge,credit'],
            'description'        => ['required', 'string', 'max:255'],
            'quantity'           => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price_usd'     => ['required', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'reason'             => ['required_if:kind,credit', 'nullable', 'string', 'max:255'],
            // Credits only, and only an item of the folio in the URL. The route
            // parameter is read null-safely: the rule audit builds this request
            // without a route.
            'reverses_item_uuid' => [
                'nullable', 'uuid', 'prohibited_unless:kind,credit',
                Rule::exists('folio_items', 'uuid')->where('folio_id', $this->route('folio')?->id),
            ],
            'idempotency_key'    => ['nullable', 'string', 'max:64'],
        ];
    }
}
