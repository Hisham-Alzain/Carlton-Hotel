<?php

namespace App\Http\Requests\Tickets;

use App\Base\BaseRequest;
use App\Enums\TicketStatus;
use Illuminate\Validation\Rule;

/**
 * PATCH /support-tickets/{ticket}/status (Phase 7, D-06). `assigned` passes
 * validation on purpose so the writer answers with `ticket_transition_invalid`;
 * whether a reason is required depends on the from-state, so the writer
 * decides that too.
 */
class UpdateTicketStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
