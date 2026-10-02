<?php

namespace App\Http\Requests\Tickets;

use App\Base\BaseRequest;

/** POST /support-tickets/{ticket}/reply (Phase 7, D-16): an internal note, never sent to the guest. */
class ReplyToTicketRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
