<?php

namespace App\Http\Requests\Tickets;

use App\Base\BaseRequest;

/**
 * POST /support-tickets/{ticket}/escalate (Phase 7, D-18). A body `level` has
 * no rule, so `validated()` drops it: the server derives the level.
 */
class EscalateTicketRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'reason'    => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
