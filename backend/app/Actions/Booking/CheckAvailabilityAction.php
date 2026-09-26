<?php

namespace App\Actions\Booking;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use Closure;
use Illuminate\Database\Eloquent\Collection;

class CheckAvailabilityAction
{
    // Single source of truth for availability — called by both public endpoints
    // and CreateReservationAction (inside the transaction lock).
    public function handle(int $roomTypeId, string $checkIn, string $checkOut): bool
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->count();

        if ($totalRooms === 0) return false;

        $occupied = $this->occupiedCount($roomTypeId, $checkIn, $checkOut);

        return $occupied < $totalRooms;
    }

    public function availableCount(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)->where('is_active', true)->count();
        $occupied   = $this->occupiedCount($roomTypeId, $checkIn, $checkOut);

        return max(0, $totalRooms - $occupied);
    }

    /**
     * The lowest-numbered room of this type with no overlapping booking.
     *
     * Guests are given a specific room at booking time, not at check-in, so the
     * booking confirmation can show "Room 801". Callers must already hold the
     * room_type lock — CreateReservationAction does.
     *
     * Filters on `is_active` only, exactly like the availability count above:
     * `rooms.status` is a housekeeping state for today and says nothing about
     * whether a room is free three weeks out. If the two predicates diverged,
     * availability could report a free room that assignment then refuses.
     */
    public function findFreeRoom(int $roomTypeId, string $checkIn, string $checkOut): ?Room
    {
        // Capacity first. Not every overlapping booking names a room — rows
        // created before rooms were reserved up front, and holds placed through
        // other paths, have a null room_id but still consume inventory. Counting
        // them keeps the old oversell guarantee intact; the room-id filter below
        // only decides *which* free room to hand out.
        if (! $this->handle($roomTypeId, $checkIn, $checkOut)) {
            return null;
        }

        return Room::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->whereNotIn('id', $this->occupiedRoomIds($roomTypeId, $checkIn, $checkOut))
            ->orderBy('number')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Whether no other holding reservation names this room over the
     * reservation's dates (D-04). The reservation's own hold never blocks it,
     * and back-to-back stays (one leaves the day the other arrives) do not
     * overlap. Used by check-in and assign-room.
     */
    public function isRoomFree(Room $room, Reservation $reservation): bool
    {
        return ! ReservationRoom::where('room_id', $room->id)
            ->where('reservation_id', '!=', $reservation->id)
            ->whereHas('reservation', $this->overlapConstraint(
                $reservation->check_in->toDateString(),
                $reservation->check_out->toDateString(),
            ))
            ->exists();
    }

    /**
     * Rooms of the type the reservation could be given, in `number` order:
     * active, not in maintenance, and named by no other holding reservation
     * over its dates. Exactly two queries, no locks (the available-rooms pick
     * list is a pure read; check-in calls it under the room_type lock).
     *
     * Capacity first, the same rule as booking: when the other overlapping
     * holds — null-room rows included — already use every active room of the
     * type, nothing is free even if a particular room looks unnamed.
     *
     * @return Collection<int, Room>
     */
    public function freeRoomsFor(Reservation $reservation, int $roomTypeId): Collection
    {
        $rooms = Room::where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'uuid', 'number', 'floor', 'status', 'room_type_id']);

        $holds = $this->overlapping(
            $roomTypeId,
            $reservation->check_in->toDateString(),
            $reservation->check_out->toDateString(),
            $reservation->id,
        )->get(['room_id']);

        if ($holds->count() >= $rooms->count()) {
            return new Collection();
        }

        $taken = $holds->pluck('room_id')->filter()->flip();

        return $rooms
            ->reject(fn (Room $room) => $room->status === RoomStatus::MAINTENANCE || $taken->has($room->id))
            ->values();
    }

    /**
     * The room check-in hands out when none was given or reserved: the
     * lowest-numbered free `available` room, else the lowest-numbered free
     * `dirty` one, never a room in maintenance; null when nothing is free.
     *
     * Check-in only (plan 03-03). Booking keeps `findFreeRoom()`, which is
     * habitability-blind by design — today's housekeeping state says nothing
     * about a stay three weeks out.
     */
    public function pickRoomFor(Reservation $reservation, int $roomTypeId): ?Room
    {
        $free = $this->freeRoomsFor($reservation, $roomTypeId);

        return $free->first(fn (Room $room) => $room->status === RoomStatus::AVAILABLE)
            ?? $free->first();
    }

    private function occupiedCount(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        return $this->overlapping($roomTypeId, $checkIn, $checkOut)->count();
    }

    /** @return array<int> */
    private function occupiedRoomIds(int $roomTypeId, string $checkIn, string $checkOut): array
    {
        return $this->overlapping($roomTypeId, $checkIn, $checkOut)
            ->whereNotNull('room_id')
            ->pluck('room_id')
            ->all();
    }

    /**
     * Reservation-room rows of the type whose reservation holds inventory over
     * the dates. `$exceptReservationId` leaves one reservation's own rows out
     * (the pick list and check-in must not count a stay against itself);
     * existing callers pass three arguments and get the same query as before.
     */
    private function overlapping(int $roomTypeId, string $checkIn, string $checkOut, ?int $exceptReservationId = null)
    {
        return ReservationRoom::where('room_type_id', $roomTypeId)
            ->when($exceptReservationId !== null, fn ($q) => $q->where('reservation_id', '!=', $exceptReservationId))
            ->whereHas('reservation', $this->overlapConstraint($checkIn, $checkOut));
    }

    /**
     * THE overlap predicate (D-04), applied to `reservations` inside a
     * `whereHas('reservation', ...)`: a holding stay whose dates intersect
     * `[checkIn, checkOut)`. Booking, the pick list, check-in and assign-room
     * all go through this one closure, so they cannot drift apart.
     */
    private function overlapConstraint(string $checkIn, string $checkOut): Closure
    {
        return function ($q) use ($checkIn, $checkOut) {
            // whereDate() strips time component so adjacent stays don't falsely collide
            // (Eloquent 'date' cast stores via fromDateTime() which may include H:i:s)
            $q->whereDate('check_in', '<', $checkOut)
              ->whereDate('check_out', '>', $checkIn)
              ->holdingInventory();
        };
    }
}
