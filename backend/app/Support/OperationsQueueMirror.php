<?php

namespace App\Support;

use App\Enums\Department;
use App\Models\HousekeepingTask;
use App\Models\ServiceRequest;
use App\Models\Ticket;

// Single source of truth for the ops_queue Firestore document shape — used by
// MirrorServiceRequestToFirestore (creation, P9), the assign/status Actions
// (P10) and MirrorHousekeepingTaskToFirestore (Phase 6) alike, so every writer
// of a given document agrees on its fields regardless of which event produced
// the write. Since Phase 6 `ops_queue` carries three status vocabularies
// (service request, ticket, housekeeping task; D-11b); consumers branch on the
// document id prefix.
class OperationsQueueMirror
{
    public static function documentId(ServiceRequest|Ticket|HousekeepingTask $item): string
    {
        return OperationsQueueType::forModel($item)->mirrorPrefix . $item->uuid;
    }

    public static function payload(ServiceRequest|Ticket|HousekeepingTask $item): array
    {
        if ($item instanceof HousekeepingTask) {
            return self::taskPayload($item);
        }

        // priority is a string enum on ServiceRequest but an int scale on
        // Ticket — normalized to one type so consumers never see it flip.
        $priority = $item instanceof ServiceRequest ? $item->priority : $item->priorityLabel();

        // Firestore takes a plain array — enum cases must be unwrapped to their
        // backing strings here, unlike API resources which JSON-serialize them.
        $shared = [
            'uuid'               => $item->uuid,
            'department'         => $item->department?->value,
            'status'             => $item->status?->value,
            'priority'           => $priority?->value,
            'guest_uuid'         => $item->guest?->uuid,
            'assigned_user_uuid' => $item->assignedUser?->uuid,
            'created_at'         => $item->created_at->toIso8601String(),
        ];

        return $item instanceof ServiceRequest
            ? $shared + ['type' => $item->type]
            : $shared + ['category' => $item->category?->value];
    }

    /** Ids, statuses and the room number only — no names or phones (T-06-06). */
    private static function taskPayload(HousekeepingTask $task): array
    {
        return [
            'uuid'               => $task->uuid,
            'department'         => Department::HOUSEKEEPING->value,
            'status'             => $task->status?->value,
            'priority'           => $task->priority?->value,
            'guest_uuid'         => $task->reservation?->guest?->uuid,
            'assigned_user_uuid' => $task->assignedUser?->uuid,
            'created_at'         => $task->created_at->toIso8601String(),
            'task_type'          => $task->type?->value,
            'room_uuid'          => $task->room?->uuid,
            'room_number'        => $task->room?->number,
        ];
    }
}
