<?php

namespace App\Actions\Booking;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Events\GuestCheckedIn;
use App\Exceptions\NoAvailabilityException;
use App\Exceptions\ReservationOutsideStayWindowException;
use App\Exceptions\ReservationStateException;
use App\Exceptions\RoomAlreadyAssignedException;
use App\Exceptions\RoomOutOfOrderException;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\HotelClock;
use Illuminate\Support\Facades\DB;

/**
 * The check-in verb: the ONLY transition from `confirmed` to `checked_in`, and
 * the only writer of `checked_in_at` (D-01). `AssignRoomAction` assigns or
 * moves a room but never checks in (D-03).
 *
 * Inside one transaction, in this order: lock the reservation, require
 * `confirmed`, enforce the hotel-local stay window (with the day-before
 * override, D-02), take the first room line, lock its room type (the same
 * serialization point booking uses, so two desks cannot hand out one room),
 * resolve the room (given → already reserved → auto-pick, D-04), then check
 * type, maintenance and overlap. Every refusal throws inside the transaction,
 * so nothing is written.
 *
 * Occupancy is derived from the reservation, so a room's housekeeping status
 * is never written here (Phase 2 D-02): a `dirty` room may be checked into.
 * GuestCheckedIn is dispatched after the commit (room-ready push, D-05).
 */
class CheckInReservationAction
{
    public function __construct(private readonly CheckAvailabilityAction $availability) {}

    public function handle(
        Reservation $reservation,
        ?Room $room,
        ?User $actor, // null = the guest's own self check-in (StayService::checkIn)
        bool $earlyCheckIn = false,
        ?string $reason = null,
    ): array {
        $locked = DB::transaction(function () use ($reservation, $room, $actor, $earlyCheckIn, $reason) {
            // Re-read under the lock: a stale model must never decide.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReservationStatus::CONFIRMED) {
                throw new ReservationStateException(__('custom.errors.reservation_state'), [
                    'status'  => $locked->status->value,
                    'allowed' => [ReservationStatus::CONFIRMED->value],
                ]);
            }

            $today      = HotelClock::today();
            $usingEarly = false;

            if (! $locked->isWithinStayWindow($today)) {
                // The override admits exactly the day before arrival, never
                // earlier and never on or after departure. Inside the window
                // the flag is ignored and nothing is logged.
                $dayBefore  = $locked->check_in->copy()->subDay()->toDateString();
                $usingEarly = $earlyCheckIn && $today->toDateString() === $dayBefore;

                if (! $usingEarly) {
                    throw new ReservationOutsideStayWindowException(
                        __('custom.errors.reservation_outside_stay_window'),
                        [
                            'check_in'  => $locked->check_in->toDateString(),
                            'check_out' => $locked->check_out->toDateString(),
                            'today'     => $today->toDateString(),
                        ],
                    );
                }
            }

            // Multi-room reservations: the first line is authoritative (D-12).
            $line = $locked->rooms()->orderBy('id')->first();
            if (! $line) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            // Lock order is always reservation, then room type.
            RoomType::withTrashed()->whereKey($line->room_type_id)->lockForUpdate()->first();

            $target = $room
                ?? $line->room
                ?? $this->availability->pickRoomFor($locked, (int) $line->room_type_id);

            if (! $target) {
                throw new NoAvailabilityException(__('custom.errors.no_availability'));
            }

            // Re-read the target room under a lock: it was resolved (explicit,
            // pre-assigned, or auto-picked) outside any lock, so a concurrent
            // PATCH .../status to maintenance could otherwise land between that
            // read and this commit. Lock order stays reservation, room type, room.
            // Room is soft-deletable, so a concurrent delete can make the re-read
            // return null: fold that into the existing "no target room" refusal.
            $target = Room::whereKey($target->id)->lockForUpdate()->first();

            if (! $target) {
                throw new NoAvailabilityException(__('custom.errors.no_availability'));
            }

            if ((int) $target->room_type_id !== (int) $line->room_type_id) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            if ($target->status === RoomStatus::MAINTENANCE) {
                throw new RoomOutOfOrderException(__('custom.errors.room_out_of_order'), [
                    'room_uuid'           => $target->uuid,
                    'housekeeping_status' => $target->status->value,
                ]);
            }

            if (! $this->availability->isRoomFree($target, $locked)) {
                throw new RoomAlreadyAssignedException(__('custom.errors.room_already_assigned'));
            }

            if ((int) $line->room_id !== (int) $target->id) {
                $line->update(['room_id' => $target->id]);
            }

            $locked->update([
                'status'        => ReservationStatus::CHECKED_IN,
                // Keep an existing stamp: the guest's arrival time is never rewritten.
                'checked_in_at' => $locked->checked_in_at ?? now(),
            ]);

            if ($usingEarly) {
                activity()
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->withProperties([
                        'reason'   => $reason,
                        'check_in' => $locked->check_in->toDateString(),
                        'today'    => $today->toDateString(),
                    ])
                    ->log('reservation.early_check_in');
            }

            return $locked;
        });

        // After commit: pushes the guest's "room ready" notification (D-05).
        event(new GuestCheckedIn($locked));

        return ['data' => $locked->fresh()->load(['rooms.room', 'rooms.roomType', 'guest']), 'code' => 200];
    }
}
