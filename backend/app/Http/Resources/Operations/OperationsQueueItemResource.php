<?php

namespace App\Http\Resources\Operations;

use App\Base\BaseResource;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class OperationsQueueItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $isServiceRequest = $item instanceof ServiceRequest;

        return [
            'type'               => $isServiceRequest ? 'service_request' : 'ticket',
            'uuid'               => $item->uuid,
            'subject'            => $isServiceRequest ? $item->type : $item->subject,
            'department'         => $item->department,
            'status'             => $item->status,
            // priority is a string enum on ServiceRequest but an int scale on
            // Ticket — normalized so this field never changes type per row.
            'priority'           => $isServiceRequest ? $item->priority : $item->priorityLabel(),
            'assigned_user_uuid' => $item->assignedUser?->uuid,
            'created_at'         => $item->created_at?->toIso8601String(),
        ];
    }
}
