<?php

namespace App\Http\Requests\Tickets;

use App\Base\BaseRequest;
use App\Enums\TicketRecoveryType;
use Illuminate\Validation\Rule;

/**
 * POST /support-tickets/{ticket}/recovery-actions (Phase 7, D-14, D-15).
 * `folio_item_uuid` is required for, and only allowed on, a folio_credit; an
 * unknown uuid is a 422 through `exists` (FA-7.07-2). Stay, credit and
 * already-linked checks are the writer's.
 */
class RecordTicketRecoveryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type'            => ['required', Rule::enum(TicketRecoveryType::class)],
            'description'     => ['required', 'string', 'min:3', 'max:1000'],
            'amount_usd'      => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999.99'],
            'folio_item_uuid' => [
                'required_if:type,'.TicketRecoveryType::FOLIO_CREDIT->value,
                'prohibited_unless:type,'.TicketRecoveryType::FOLIO_CREDIT->value,
                'nullable', 'uuid', 'exists:folio_items,uuid',
            ],
        ];
    }
}
