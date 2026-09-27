<?php

namespace App\Listeners;

use App\Actions\Housekeeping\CreateHousekeepingTaskAction;
use App\Enums\Department;
use App\Enums\HousekeepingTaskType;
use App\Events\ServiceRequestPlaced;
use App\Models\ReservationRoom;
use App\Models\Room;

/**
 * Opens the request task for a service request routed to the housekeeping
 * department (Phase 6, D-10, consultant override: linked by
 * `service_request_id`, one task per request).
 *
 * Synchronous, and it never throws on business grounds: the request is already
 * committed when ServiceRequestPlaced fires, so a failure is reported and the
 * request stays on the operations queue untouched. A reservation with no
 * assigned room yet is skipped and logged (`housekeeping_task_skipped`).
 * Auto-discovered from the type hint.
 */
class CreateRequestTaskOnServiceRequestPlaced
{
    public function __construct(private readonly CreateHousekeepingTaskAction $createTask) {}

    public function handle(ServiceRequestPlaced $event): void
    {
        $request = $event->request;

        if ($request->department !== Department::HOUSEKEEPING) {
            return;
        }

        $roomId = ReservationRoom::where('reservation_id', $request->reservation_id)
            ->whereNotNull('room_id')
            ->orderBy('id')
            ->value('room_id');

        $room = $roomId ? Room::find($roomId) : null;

        if (! $room) {
            activity()
                ->performedOn($request)
                ->withProperties(['reason' => 'no_room'])
                ->log('housekeeping_task_skipped');

            return;
        }

        $request->loadMissing('serviceItem');

        try {
            $this->createTask->ensureOpen($room, HousekeepingTaskType::REQUEST, [
                'reservation_id'  => $request->reservation_id,
                'priority'        => $request->priority,
                'due_at'          => $request->created_at->copy()->addMinutes($request->serviceItem?->expected_minutes ?? 60),
                'notes'           => $request->notes,
                'service_request' => $request,
                'reason'          => 'service_request',
            ], null);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
