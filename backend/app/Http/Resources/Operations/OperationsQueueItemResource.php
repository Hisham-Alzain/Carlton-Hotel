<?php

namespace App\Http\Resources\Operations;

use App\Base\BaseResource;
use App\Enums\Department;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Support\OperationsQueueType;
use Illuminate\Http\Request;

/**
 * One row of the merged operations queue: a service request, a ticket or a
 * housekeeping task (Phase 6, D-14). `room_number` and `allowed_statuses` are
 * on every row; relations must be loaded by OperationsQueueType::baseQuery().
 *
 * `queue_type` (Phase 7, OPS-03, D-24) is the URL segment
 * (service-requests | tickets | housekeeping-tasks): the dashboard builds
 * `/operations/queue/{queue_type}/{uuid}/…` from it. No `{id}` alias routes.
 */
class OperationsQueueItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $type = OperationsQueueType::forModel($item);

        return [
            'type'               => $type->itemType,
            'queue_type'         => $type->segment,
            'uuid'               => $item->uuid,
            'subject'            => match (true) {
                $item instanceof ServiceRequest   => $item->type,
                $item instanceof HousekeepingTask => $item->type->value,
                default                           => $item->subject,
            },
            'department'         => $item instanceof HousekeepingTask
                ? Department::HOUSEKEEPING->value
                : $item->department?->value,
            'status'             => $item->status?->value,
            // priority is a string enum on ServiceRequest/HousekeepingTask but
            // an int scale on Ticket — normalized so this field never changes
            // type per row.
            'priority'           => ($item instanceof ServiceRequest || $item instanceof HousekeepingTask
                ? $item->priority
                : $item->priorityLabel())?->value,
            'assigned_user_uuid' => $item->assignedUser?->uuid,
            'created_at'         => $item->created_at?->toIso8601String(),
            'room_number'        => $type->roomNumber($item),
            'allowed_statuses'   => $type->allowedStatuses($item),
        ];
    }
}
