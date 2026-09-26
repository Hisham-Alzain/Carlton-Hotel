<?php

namespace App\Support;

use App\Enums\GuestStayStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GuestEntitlement
{
    // Booked reservations covering the current/upcoming window (confirmed or checked_in, not yet past checkout).
    public static function bookedReservations(Guest $guest)
    {
        $today = now()->startOfDay();

        return $guest->activeReservations()
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN])
            ->whereDate('check_out', '>=', $today)
            ->get();
    }

    public static function hasBooking(Guest $guest): bool
    {
        return self::bookedReservations($guest)->isNotEmpty();
    }

    public static function isCheckedIn(Guest $guest): bool
    {
        return self::bookedReservations($guest)
            ->contains(fn (Reservation $r) => $r->status === ReservationStatus::CHECKED_IN);
    }

    // The guest's current booked/checked-in reservation — server-resolved, never taken from client input.
    public static function currentReservation(Guest $guest): ?Reservation
    {
        return self::bookedReservations($guest)->sortByDesc('check_in')->first();
    }

    // ── Phase 4 (D-02, D-03): staff-side stay status and target reservation ──
    //
    // Additive siblings only: the four methods above keep their semantics for
    // their existing call sites (FA-06-1 stays deferred per D-03).

    /** Statuses that can still make up a live stay. */
    private const LIVE_STATUSES = [
        ReservationStatus::PENDING,
        ReservationStatus::CONFIRMED,
        ReservationStatus::CHECKED_IN,
    ];

    /**
     * Limit a reservations query to the guest's live set on hotel-local day T:
     * pending | confirmed | checked_in, where a checked_in stay counts whatever
     * its check_out (the guest is in the building) and the others only while
     * check_out >= T. Ordered check_in asc, id asc. Shared by the directory
     * eager load and the profile; `isLive()` is its PHP twin.
     */
    public static function constrainLive(Builder|HasMany $query, CarbonImmutable $today): Builder|HasMany
    {
        return $query
            ->whereIn('status', self::LIVE_STATUSES)
            ->where(function ($q) use ($today) {
                $q->where('status', ReservationStatus::CHECKED_IN)
                  ->orWhereDate('check_out', '>=', $today->toDateString());
            })
            ->orderBy('check_in')
            ->orderBy('id');
    }

    /**
     * The `constrainLive()` rule in PHP, for filtering an already-loaded set.
     * Must stay equivalent to it (GuestStayStatusTest compares the two).
     */
    public static function isLive(Reservation $reservation, CarbonImmutable $today): bool
    {
        if (! in_array($reservation->status, self::LIVE_STATUSES, true)) {
            return false;
        }

        return $reservation->status === ReservationStatus::CHECKED_IN
            || $reservation->check_out->toDateString() >= $today->toDateString();
    }

    /**
     * The row status over a guest's live set (D-02), by precedence
     * departing > in_house > arriving > upcoming > past > none.
     *
     * Two live cases match none of D-02's six predicates (FA-4.07-1): a
     * confirmed stay whose check_in has passed without check-in (an overdue
     * arrival) reads `arriving`, and a pending stay arriving today (or
     * overdue) reads `upcoming`.
     *
     * @param  Collection<int, Reservation>  $live    the guest's live set
     * @param  bool                          $hasAny  whether the guest has any reservation at all
     */
    public static function stayStatus(Collection $live, bool $hasAny, CarbonImmutable $today): GuestStayStatus
    {
        $t = $today->toDateString();

        $checkedIn = $live->filter(fn (Reservation $r) => $r->status === ReservationStatus::CHECKED_IN);

        if ($checkedIn->contains(fn (Reservation $r) => $r->check_out->toDateString() === $t)) {
            return GuestStayStatus::DEPARTING;
        }

        if ($checkedIn->isNotEmpty()) {
            return GuestStayStatus::IN_HOUSE;
        }

        if ($live->contains(fn (Reservation $r) => $r->status === ReservationStatus::CONFIRMED
            && $r->check_in->toDateString() === $t)) {
            return GuestStayStatus::ARRIVING;
        }

        if ($live->contains(fn (Reservation $r) => in_array($r->status, [ReservationStatus::CONFIRMED, ReservationStatus::PENDING], true)
            && $r->check_in->toDateString() > $t)) {
            return GuestStayStatus::UPCOMING;
        }

        if ($live->isEmpty()) {
            return $hasAny ? GuestStayStatus::PAST : GuestStayStatus::NONE;
        }

        // FA-4.07-1 fallbacks: only overdue or same-day pending arrivals remain.
        return $live->contains(fn (Reservation $r) => $r->status === ReservationStatus::CONFIRMED)
            ? GuestStayStatus::ARRIVING
            : GuestStayStatus::UPCOMING;
    }

    /**
     * The reservation that matters now (D-03), from a live set: the checked_in
     * stay (latest check_in, then highest id, if several), else the next
     * arrival — the earliest confirmed | pending stay with check_in >= T, id as
     * tie-break. Never the latest booking.
     *
     * @param  Collection<int, Reservation>  $live
     */
    public static function targetFrom(Collection $live, CarbonImmutable $today): ?Reservation
    {
        $inHouse = $live
            ->filter(fn (Reservation $r) => $r->status === ReservationStatus::CHECKED_IN)
            ->sort(fn (Reservation $a, Reservation $b) => [$b->check_in->toDateString(), $b->id] <=> [$a->check_in->toDateString(), $a->id])
            ->first();

        if ($inHouse !== null) {
            return $inHouse;
        }

        $t = $today->toDateString();

        return $live
            ->filter(fn (Reservation $r) => in_array($r->status, [ReservationStatus::CONFIRMED, ReservationStatus::PENDING], true)
                && $r->check_in->toDateString() >= $t)
            ->sort(fn (Reservation $a, Reservation $b) => [$a->check_in->toDateString(), $a->id] <=> [$b->check_in->toDateString(), $b->id])
            ->first();
    }

    /**
     * The guest's target reservation on the hotel-local date (D-03). Differs
     * from currentReservation(): in-house first, else the earliest
     * confirmed|pending arrival >= today, id tie-break; FA-06-1 alignment
     * deferred per D-03.
     */
    public static function targetReservation(Guest $guest): ?Reservation
    {
        $today = HotelClock::today();

        return self::targetFrom(self::constrainLive($guest->reservations(), $today)->get(), $today);
    }
}
