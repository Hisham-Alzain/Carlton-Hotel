<?php

namespace App\Support;

use App\Models\ServiceRequest;
use App\Models\Ticket;

// Single source of truth for the ops_queue Firestore document shape — used by
// MirrorServiceRequestToFirestore (creation, P9) and the assign/status
// Actions (P10) alike, so every writer of a given document agrees on its
// fields regardless of which event produced the write.
class OperationsQueueMirror
{
    public static function documentId(ServiceRequest|Ticket $item): string
    {
        return $item instanceof ServiceRequest ? 'service_request_' . $item->uuid : 'ticket_' . $item->uuid;
    }

    public static function payload(ServiceRequest|Ticket $item): array
    {
        // priority is a string enum on ServiceRequest but an int scale on
        // Ticket — normalized to one type so consumers never see it flip.
        $priority = $item instanceof ServiceRequest ? $item->priority : $item->priorityLabel();

        $shared = [
            'uuid'               => $item->uuid,
            'department'         => $item->department,
            'status'             => $item->status,
            'priority'           => $priority,
            'guest_uuid'         => $item->guest?->uuid,
            'assigned_user_uuid' => $item->assignedUser?->uuid,
            'created_at'         => $item->created_at->toIso8601String(),
        ];

        return $item instanceof ServiceRequest
            ? $shared + ['type' => $item->type]
            : $shared + ['category' => $item->category];
    }
}
