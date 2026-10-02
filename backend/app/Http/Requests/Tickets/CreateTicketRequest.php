<?php

namespace App\Http\Requests\Tickets;

use App\Base\BaseRequest;
use App\Enums\Department;
use App\Enums\ServiceRequestPriority;
use App\Enums\TicketCategory;
use Illuminate\Validation\Rule;

/**
 * POST /support-tickets (Phase 7, D-13). Source, status, creator and the
 * chatbot conversation are server-set, so they are deliberately absent from
 * the rules and never reach `validated()` (D-04, D-05). Priority takes labels
 * low|normal|high only (D-12). Room lookups skip trashed rooms.
 */
class CreateTicketRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'subject'          => ['required', 'string', 'min:3', 'max:150'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'category'         => ['required', Rule::enum(TicketCategory::class)],
            'priority'         => ['nullable', Rule::enum(ServiceRequestPriority::class)],
            'department'       => ['nullable', Rule::enum(Department::class)],
            'guest_uuid'       => ['nullable', 'uuid', 'exists:guests,uuid'],
            'reservation_uuid' => ['nullable', 'uuid', 'exists:reservations,uuid'],
            'room_uuid'        => ['nullable', 'uuid', 'exists:rooms,uuid'],
        ];
    }
}
