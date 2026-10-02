<?php

namespace App\Http\Requests\Tickets;

use App\Base\BaseRequest;

/** PATCH /support-tickets/{ticket}/assign (Phase 7, D-08); eligibility is checked by the writer (D-09). */
class AssignTicketRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
        ];
    }
}
