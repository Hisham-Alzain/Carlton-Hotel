<?php

namespace App\Listeners;

use App\Actions\Housekeeping\CreateHousekeepingTaskAction;
use App\Enums\HousekeepingTaskType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestPriority;
use App\Events\ReservationCheckedOut;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Support\HotelClock;

/**
 * Opens one turnover task per distinct assigned room when a stay checks out
 * (Phase 6, D-09) — staff plain, staff forced and guest express alike.
 *
 * Deliberately synchronous (not ShouldQueue), like RevokeDigitalKeyOnCheckOut:
 * the task exists when the check-out response returns. The event is
 * after-commit, so the check-out is already durable here; a failure for one
 * room is reported and never thrown (a thrown exception would turn a committed
 * check-out into a 500 and invite a retry refused with `reservation_state`).
 * Recovery: `housekeeping:reconcile` lists the dirty room and
 * `POST /housekeeping/tasks` opens its task (D-08). Tasks are system-created,
 * so the actor is always null. Dedupe (D-04) is ensureOpen's.
 * Auto-discovered from the type hint.
 */
class CreateTurnoverTaskOnCheckOut
{
    public function __construct(private readonly CreateHousekeepingTaskAction $createTask) {}

    public function handle(ReservationCheckedOut $event): void
    {
        $reservation = $event->reservation;

        $roomIds = $reservation->rooms()->whereNotNull('room_id')->pluck('room_id')->unique()->values();

        if ($roomIds->isEmpty()) {
            return;
        }

        $rooms = Room::whereIn('id', $roomIds)->orderBy('id')->get();

        // A room is awaited when a confirmed stay arriving today (hotel-local) holds it.
        $arriving = ReservationRoom::whereIn('room_id', $roomIds)
            ->whereHas('reservation', fn ($q) => $q
                ->where('status', ReservationStatus::CONFIRMED->value)
                ->whereDate('check_in', HotelClock::today()->toDateString()))
            ->pluck('room_id')
            ->unique()
            ->all();

        $dueAt = now()->addMinutes((int) config('hotel.turnover_sla_minutes', 120));

        foreach ($rooms as $room) {
            try {
                $this->createTask->ensureOpen($room, HousekeepingTaskType::TURNOVER, [
                    'reservation_id' => $reservation->id,
                    'priority'       => in_array($room->id, $arriving, true) ? ServiceRequestPriority::HIGH : ServiceRequestPriority::NORMAL,
                    'due_at'         => $dueAt,
                    'reason'         => 'check_out',
                ], null);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
