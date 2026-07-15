<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Models\ServiceRequest;
use App\Models\Ticket;

class UpdateRequestStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        $statuses = $this->route('type') === 'tickets' ? Ticket::STATUSES : ServiceRequest::STATUSES;

        return [
            'status' => ['required', 'string', 'in:' . implode(',', $statuses)],
        ];
    }
}
