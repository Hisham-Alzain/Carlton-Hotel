<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketStatus;
use Illuminate\Validation\Rule;

class UpdateRequestStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        $enum = $this->route('type') === 'tickets' ? TicketStatus::class : ServiceRequestStatus::class;

        return [
            'status' => ['required', Rule::enum($enum)],
        ];
    }
}
