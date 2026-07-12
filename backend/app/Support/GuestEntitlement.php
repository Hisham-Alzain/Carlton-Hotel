<?php

namespace App\Support;

use App\Models\Guest;
use App\Models\Reservation;

class GuestEntitlement
{
    // Booked reservations covering the current/upcoming window (confirmed or checked_in, not yet past checkout).
    public static function bookedReservations(Guest $guest)
    {
        $today = now()->startOfDay();

        return $guest->activeReservations()
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
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
            ->contains(fn (Reservation $r) => $r->status === Reservation::STATUS_CHECKED_IN);
    }

    // The guest's current booked/checked-in reservation — server-resolved, never taken from client input.
    public static function currentReservation(Guest $guest): ?Reservation
    {
        return self::bookedReservations($guest)->sortByDesc('check_in')->first();
    }
}
