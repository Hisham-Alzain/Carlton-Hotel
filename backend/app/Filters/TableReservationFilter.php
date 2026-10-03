<?php

namespace App\Filters;

use App\Base\BaseFilter;
use App\Models\RestaurantTable;
use App\Support\HotelClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Filters for the staff table-reservation list `GET /cms/table-reservations`
 * (Phase 8, D-24).
 *
 * DSL field: `status` (eq, in) — an unknown value matches nothing.
 *
 * Custom params:
 *  - `venue` / `table`: a dining-venue / restaurant-table uuid. An unknown
 *    uuid (or a non-uuid) is an empty page, not a 422 (filter semantics).
 *    Matched through a subquery that ignores soft deletes, so a trashed
 *    venue's reservations stay listable.
 *  - `date`: a hotel-local `Y-m-d` day, or
 *  - `from` + `to`: hotel-local `Y-m-d` days, inclusive, `to ≥ from`, at most
 *    31 days. `date` together with either is a 422; so is one without the other.
 *  - none of the three: the hotel-local today. The list is never unbounded.
 *
 * Sort: `scheduled_at`, `guest_count` (BaseFilter `sort` / `sort_dir`); the
 * default order (`scheduled_at`, `id` ascending) lives in TableReservationService.
 */
class TableReservationFilter extends BaseFilter
{
    private const MAX_RANGE_DAYS = 31;

    protected array $safeParms = [
        'status' => ['eq', 'in'],
    ];

    protected array $sortable = ['scheduled_at', 'guest_count'];

    protected function applyConditions(Builder $query): void
    {
        parent::applyConditions($query);

        $this->applyVenue($query);
        $this->applyTable($query);
        $this->applyWindow($query);
    }

    private function applyVenue(Builder $query): void
    {
        $value = $this->params['venue'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        $query->whereIn('bookable_id', RestaurantTable::query()
            ->select('restaurant_tables.id')
            ->join('dining_venues', 'dining_venues.id', '=', 'restaurant_tables.dining_venue_id')
            ->where('dining_venues.uuid', $this->uuidOrNothing($value)));
    }

    private function applyTable(Builder $query): void
    {
        $value = $this->params['table'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        $query->whereIn('bookable_id', RestaurantTable::query()
            ->select('id')
            ->where('uuid', $this->uuidOrNothing($value)));
    }

    private function applyWindow(Builder $query): void
    {
        $date = $this->params['date'] ?? null;
        $from = $this->params['from'] ?? null;
        $to   = $this->params['to'] ?? null;

        $hasDate  = ! $this->isBlank($date);
        $hasRange = ! $this->isBlank($from) || ! $this->isBlank($to);

        if ($hasDate && $hasRange) {
            throw $this->reject('date', __('custom.validation.prohibits', ['attribute' => 'date', 'other' => 'from, to']));
        }

        if ($hasRange) {
            if ($this->isBlank($from)) {
                throw $this->reject('from', __('custom.validation.required', ['attribute' => 'from']));
            }
            if ($this->isBlank($to)) {
                throw $this->reject('to', __('custom.validation.required', ['attribute' => 'to']));
            }

            [$start]    = $this->day('from', $from);
            [$toStart, $end] = $this->day('to', $to);

            if ($toStart->lessThan($start)) {
                throw $this->reject('to', __('custom.validation.after_or_equal', ['attribute' => 'to', 'date' => 'from']));
            }

            // Calendar days, inclusive; compared on the local dates so a DST day still counts as one.
            $days = CarbonImmutable::parse((string) $from)->diffInDays(CarbonImmutable::parse((string) $to)) + 1;
            if ($days > self::MAX_RANGE_DAYS) {
                throw $this->reject('to', __('custom.validation.date_range_max', ['days' => self::MAX_RANGE_DAYS]));
            }

            $this->between($query, $start, $end);

            return;
        }

        [$start, $end] = $hasDate
            ? $this->day('date', $date)
            : HotelClock::dayWindow(HotelClock::today()->toDateString());

        $this->between($query, $start, $end);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function day(string $key, mixed $value): array
    {
        try {
            return HotelClock::dayWindow(is_string($value) ? $value : '');
        } catch (InvalidArgumentException) {
            throw $this->reject($key, __('custom.validation.date_format', ['attribute' => $key, 'format' => 'Y-m-d']));
        }
    }

    private function between(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->where('service_bookings.scheduled_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('service_bookings.scheduled_at', '<', $end->format('Y-m-d H:i:s'));
    }

    /** A non-uuid can never match, so it is compared as an impossible value instead of rejected. */
    private function uuidOrNothing(mixed $value): string
    {
        return is_string($value) && Str::isUuid($value) ? $value : '';
    }
}
