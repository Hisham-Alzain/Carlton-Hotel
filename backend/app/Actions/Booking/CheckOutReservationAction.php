<?php

namespace App\Actions\Booking;

use App\Actions\Cms\UpdateRoomStatusAction;
use App\Actions\Folio\GenerateFolioAction;
use App\Enums\CheckOutMode;
use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Events\ReservationCheckedOut;
use App\Exceptions\FolioUnsettledException;
use App\Exceptions\ReservationStateException;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single check-out path, for staff (NONE / STAFF_FORCE) and for the
 * guest's express checkout (GUEST_EXPRESS) alike (D-06..D-09).
 *
 * The folio gate is skipped only by an explicit mode, never by a missing
 * check, and every successful path defines the lock, `checked_out_at`, the
 * room-dirty write and the event exactly once:
 *
 *  1. lock the reservation, require `checked_in`;
 *  2. lock the folio row (the same row SettleFolioAction locks, so settle and
 *     check-out never interleave); create it, or refresh an open one's items,
 *     under the lock — never trusting a preloaded `folio` relation;
 *  3. an open folio with mode NONE is refused with `folio_unsettled`. That
 *     refusal is raised AFTER the transaction commits, so the folio created or
 *     refreshed in step 2 is kept and `context.folio_uuid` names a real folio
 *     the desk can settle (FA-05-7); nothing else is written on that path;
 *  4. stamp `checked_out` / `checked_out_at` (no date guard: same-day and early
 *     departure are allowed);
 *  5. ensure every assigned room is dirty through UpdateRoomStatusAction, the
 *     single writer of room status, as a system change (null actor);
 *  6. log the override (STAFF_FORCE with the staff causer and reason;
 *     GUEST_EXPRESS with no causer at all) and dispatch ReservationCheckedOut,
 *     which the dispatcher defers until the outermost transaction commits.
 *
 * The folio's status, settled_at and payments are never written here.
 */
class CheckOutReservationAction
{
    private const ROOM_STATUS_REASON = 'check-out';

    public function __construct(
        private readonly GenerateFolioAction $generateFolio,
        private readonly UpdateRoomStatusAction $updateRoomStatus,
    ) {}

    public function handle(Reservation $reservation, CheckOutMode $mode, ?User $actor, ?string $reason = null): array
    {
        $outcome = DB::transaction(function () use ($reservation, $mode, $actor, $reason) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReservationStatus::CHECKED_IN) {
                throw new ReservationStateException(__('custom.errors.reservation_state'), [
                    'status'  => $locked->status->value,
                    'allowed' => [ReservationStatus::CHECKED_IN->value],
                ]);
            }

            $folio = Folio::where('reservation_id', $locked->id)->lockForUpdate()->first();

            if (! $folio || $folio->status === FolioStatus::OPEN) {
                // Still checked_in here, so GenerateFolioAction builds / refreshes items.
                $this->generateFolio->handle($locked);
                $folio = Folio::where('reservation_id', $locked->id)->lockForUpdate()->firstOrFail();
            }

            // A settled folio ignores force (D-07): report it as an ordinary check-out.
            $effective = $folio->status === FolioStatus::SETTLED && $mode === CheckOutMode::STAFF_FORCE
                ? CheckOutMode::NONE
                : $mode;

            if ($folio->status === FolioStatus::OPEN && $effective === CheckOutMode::NONE) {
                // Commit the folio work; the refusal is thrown after the transaction.
                return ['refused' => $folio];
            }

            $locked->update([
                'status'         => ReservationStatus::CHECKED_OUT,
                'checked_out_at' => now(),
            ]);

            $this->dirtyAssignedRooms($locked);

            $folioSummary = [
                'folio_uuid'   => $folio->uuid,
                'folio_status' => $folio->status->value,
                'total_usd'    => (string) $folio->total_usd,
            ];

            if ($effective === CheckOutMode::STAFF_FORCE) {
                activity()
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->withProperties([...$folioSummary, 'reason' => $reason])
                    ->log('reservation.check_out_forced');
            } elseif ($effective === CheckOutMode::GUEST_EXPRESS) {
                // causedBy(null) would keep the authenticated guest as causer;
                // the guest marker must carry no causer at all (D-08).
                activity()
                    ->performedOn($locked)
                    ->causedByAnonymous()
                    ->withProperties($folioSummary)
                    ->log('reservation.check_out_guest_express');
            }

            // ShouldDispatchAfterCommit: deferred until the outermost commit.
            ReservationCheckedOut::dispatch($locked, $effective, $actor?->getKey());

            return ['checked_out' => $locked];
        });

        if (isset($outcome['refused'])) {
            $folio = $outcome['refused'];

            throw new FolioUnsettledException(__('custom.errors.folio_unsettled'), [
                'folio_uuid' => $folio->uuid,
                'total_usd'  => (string) $folio->total_usd,
                'can_force'  => (bool) $actor?->can('folios.settle'),
            ]);
        }

        return [
            'data' => $outcome['checked_out']->fresh()->load(['rooms.room', 'rooms.roomType', 'guest', 'folio']),
            'code' => 200,
        ];
    }

    /**
     * Ensure-dirty for every distinct room on the reservation's lines: rooms
     * already dirty are left alone (no history row), lines without a room and
     * rooms that no longer exist are skipped, and maintenance → dirty is an
     * allowed Phase 2 transition. Rooms are locked before their status is read.
     */
    private function dirtyAssignedRooms(Reservation $reservation): void
    {
        $roomIds = $reservation->rooms()->whereNotNull('room_id')->pluck('room_id')->unique()->values();

        $rooms = Room::whereIn('id', $roomIds)->orderBy('id')->lockForUpdate()->get();

        foreach ($rooms as $room) {
            if ($room->status !== RoomStatus::DIRTY) {
                $this->updateRoomStatus->handle($room, RoomStatus::DIRTY, self::ROOM_STATUS_REASON, null);
            }
        }
    }
}
