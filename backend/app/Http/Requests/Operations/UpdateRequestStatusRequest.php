<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Enums\ServiceRequestStatus;
use App\Support\OperationsQueueType;
use Illuminate\Validation\Rule;

class UpdateRequestStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        // Each queue type validates against its own status enum (D-12). An
        // unknown segment keeps validating as a request status so the service
        // still answers 404 for it.
        $enum = OperationsQueueType::tryFromSegment((string) $this->route('type'))?->statusEnum
            ?? ServiceRequestStatus::class;

        return [
            'status' => ['required', Rule::enum($enum)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
