<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use Illuminate\Validation\Rule;

/**
 * `PATCH /departure-services/{uuid}/status` (Phase 6, D-22). `status` is
 * checked here against the union of the booking and request vocabularies; the
 * service then checks it against the resolved source's own family.
 */
class UpdateDepartureServiceStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status'      => ['required', 'string', Rule::in(array_values(array_unique(array_merge(
                ServiceBookingStatus::values(),
                ServiceRequestStatus::values(),
            ))))],
            'reason'      => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', Rule::in(['service_booking', 'service_request', 'reservation'])],
        ];
    }
}
