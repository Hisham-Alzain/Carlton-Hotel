<?php

namespace App\Http\Resources\Housekeeping;

use App\Base\BaseResource;
use App\Enums\HousekeepingTaskStatus;
use App\Models\HousekeepingTaskStatusHistory;
use Illuminate\Http\Request;

/**
 * A housekeeping task on the staff board (Phase 6, HK-01).
 *
 * Carries room and stay identifiers only — no personal data about whoever
 * occupies the room (T-06-04). `allowed_statuses` lists the D-05 targets from
 * the current status, so the dashboard never re-implements the state machine.
 * `history` appears on show only (newest first, at most 10). No queries here:
 * every relation is eager-loaded by HousekeepingTaskService.
 */
class HousekeepingTaskResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                 => $this->uuid,
            'type'                 => $this->type->value,
            'status'               => $this->status->value,
            'priority'             => $this->priority->value,
            'notes'                => $this->notes,
            'room'                 => $this->whenLoaded('room', fn () => $this->room ? [
                'uuid'   => $this->room->uuid,
                'number' => $this->room->number,
                'floor'  => $this->room->floor,
                'status' => $this->room->status?->value,
            ] : null),
            'reservation'          => $this->whenLoaded('reservation', fn () => $this->reservation ? [
                'uuid'         => $this->reservation->uuid,
                'booking_code' => $this->reservation->booking_code,
                'check_out'    => $this->reservation->check_out?->toDateString(),
            ] : null),
            'assigned_user'        => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'uuid' => $this->assignedUser->uuid,
                'name' => $this->assignedUser->name,
            ] : null),
            'service_request_uuid' => $this->whenLoaded('serviceRequest', fn () => $this->serviceRequest?->uuid),
            'due_at'               => $this->due_at?->toIso8601String(),
            'started_at'           => $this->started_at?->toIso8601String(),
            'completed_at'         => $this->completed_at?->toIso8601String(),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
            'allowed_statuses'     => array_map(
                static fn (HousekeepingTaskStatus $s) => $s->value,
                $this->status->allowedTargets(),
            ),
            'history'              => $this->whenLoaded('history', fn () => $this->history->map(
                static fn (HousekeepingTaskStatusHistory $row) => [
                    'from_status' => $row->from_status,
                    'to_status'   => $row->to_status,
                    'reason'      => $row->reason,
                    'changed_by'  => $row->changedBy ? ['uuid' => $row->changedBy->uuid, 'name' => $row->changedBy->name] : null,
                    'created_at'  => $row->created_at?->toIso8601String(),
                ],
            )->values()->all()),
        ];
    }
}
