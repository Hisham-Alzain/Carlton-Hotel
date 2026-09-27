<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

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
     * A hotel-local calendar day as a half-open UTC window [start, end):
     * start inclusive, end exclusive. The day is added before converting to
     * UTC, so a DST change inside the day stays correct. Consumers: the
     * housekeeping board's `due_date` (HK-01), the service request board's
     * `date` (SVC-01) and the departure services day (SVC-02).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws InvalidArgumentException when `$date` is not a strict, real `Y-m-d`
     */
    public static function dayWindow(string $date): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());
        } catch (\Throwable) {
            $start = null;
        }

        // The round trip rejects overflowed dates (`2027-13-01` → 2028-01-01).
        if (! $start instanceof CarbonImmutable || $start->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Not a Y-m-d date: {$date}");
        }

        return [$start->utc(), $start->addDay()->utc()];
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
