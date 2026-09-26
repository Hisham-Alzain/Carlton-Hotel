<?php

namespace App\Filters;

use App\Base\BaseFilter;
use App\Enums\GuestStayStatus;
use App\Enums\ReservationStatus;
use App\Support\HotelClock;
use Illuminate\Database\Eloquent\Builder;

/**
 * The staff guest directory (Phase 4, D-02).
 *
 * Extends BaseFilter directly: guests carry no translatable text and no
 * `sort_order`. On top of the column DSL it adds one derived filter,
 * `?stay_status=`, evaluated against the hotel-local date with EXISTS
 * subqueries — never a per-row loop. The six predicates are non-exclusive
 * (an `in_house` filter also lists departing guests); the row's own
 * `stay_status` is the precedence-based one (GuestEntitlement::stayStatus).
 */
class GuestFilter extends BaseFilter
{
    protected array $searchable = ['name', 'first_name', 'last_name', 'phone', 'email'];

    protected array $safeParms = [
        'phone'            => ['eq', 'like'],
        'email'            => ['eq', 'like'],
        'preferred_locale' => ['eq', 'in'],
    ];

    protected array $sortable = ['name', 'last_name', 'created_at'];

    public function apply(Builder $query): Builder
    {
        parent::apply($query);
        $this->applyStayStatus($query);

        return $query;
    }

    protected function applyStayStatus(Builder $query): void
    {
        $raw = $this->params['stay_status'] ?? null;

        // An empty value is the "All" option: no filter.
        if ($this->isBlank($raw)) {
            return;
        }

        // Unknown values and arrays are typos, not hints: 422 on stay_status.
        $status = is_string($raw) ? GuestStayStatus::tryFrom($raw) : null;

        if ($status === null) {
            throw $this->reject('stay_status', __('custom.validation.in', ['attribute' => 'stay_status']));
        }

        $t = HotelClock::today()->toDateString();

        match ($status) {
            GuestStayStatus::IN_HOUSE => $query->whereHas('reservations', fn (Builder $r) => $r
                ->where('status', ReservationStatus::CHECKED_IN)),

            GuestStayStatus::DEPARTING => $query->whereHas('reservations', fn (Builder $r) => $r
                ->where('status', ReservationStatus::CHECKED_IN)
                ->whereDate('check_out', '=', $t)),

            GuestStayStatus::ARRIVING => $query->whereHas('reservations', fn (Builder $r) => $r
                ->where('status', ReservationStatus::CONFIRMED)
                ->whereDate('check_in', '=', $t)),

            GuestStayStatus::UPCOMING => $query->whereHas('reservations', fn (Builder $r) => $r
                ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::PENDING])
                ->whereDate('check_in', '>', $t)),

            GuestStayStatus::PAST => $query
                ->has('reservations')
                ->whereDoesntHave('reservations', fn (Builder $r) => $r
                    ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN])
                    ->whereDate('check_out', '>=', $t)),

            GuestStayStatus::NONE => $query->doesntHave('reservations'),
        };
    }
}
