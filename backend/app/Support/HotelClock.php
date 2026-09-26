<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The hotel-local business date (Phase 3, D-02).
 *
 * Every business-date decision — the check-in stay window, the room board's
 * default date, the grids' default start — must use `today()`, never
 * `now()` / `today()` in the app timezone (UTC): near midnight the two name
 * different days, and the desk and the board would disagree about "today".
 */
final class HotelClock
{
    /** The configured IANA timezone (`hotel.timezone`, env `HOTEL_TIMEZONE`). */
    public static function timezone(): string
    {
        return (string) config('hotel.timezone');
    }

    /** Start of the current day in the hotel's timezone. Honours Carbon test time. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }

    /**
     * The instant a stay ends: its check-out date at `hotel.check_out_time`
     * in the hotel timezone, returned in UTC. The digital key expires then
     * (Phase 4, D-11).
     */
    public static function checkOutAt(CarbonInterface $checkOutDate): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $checkOutDate->toDateString() . ' ' . config('hotel.check_out_time'),
            self::timezone(),
        )->utc();
    }
}
