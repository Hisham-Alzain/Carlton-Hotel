<?php

namespace App\Filters;

use App\Base\BaseFilter;
use App\Support\HotelClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Filters for the staff service-request board `GET /cms/service-requests`
 * (Phase 6, SVC-01, D-15).
 *
 * DSL fields: `status`, `department`, `priority`, `type` (eq, in) — an unknown
 * value matches nothing; `created_at` (gte, lte), parsed as an instant and
 * compared in UTC.
 *
 * Custom params, each blank = no filter:
 *  - `assignee`: a staff uuid or `unassigned`; anything else is a 422;
 *  - `room`: a room number, matched through the reservation's lines;
 *  - `date`: `Y-m-d`, the hotel-local day of `created_at` as a half-open UTC
 *    window (`HotelClock::dayWindow()`); anything else is a 422;
 *  - `guest`: a guest uuid (exact), otherwise a case-insensitive fragment of
 *    the guest's name, first name or last name (FA-6.06-2).
 *
 * Sort: `created_at`, `status`, `priority` (by rank high > normal > low), always
 * with `id` descending as the tiebreak. The default order lives in
 * ServiceRequestBoardService.
 */
class ServiceRequestFilter extends BaseFilter
{
    protected array $safeParms = [
        'status'     => ['eq', 'in'],
        'department' => ['eq', 'in'],
        'priority'   => ['eq', 'in'],
        'type'       => ['eq', 'in'],
        'created_at' => ['gte', 'lte'],
    ];

    protected array $sortable = ['created_at', 'priority', 'status'];

    private const PRIORITY_RANK = "case service_requests.priority when 'high' then 3 when 'normal' then 2 when 'low' then 1 else 0 end";

    protected function applyConditions(Builder $query): void
    {
        parent::applyConditions($query);

        $this->applyAssignee($query);
        $this->applyRoom($query);
        $this->applyDate($query);
        $this->applyGuest($query);
    }

    /** `created_at` bounds are instants: parse and compare in UTC, in the stored format. */
    protected function cast(string $field, mixed $value, string $key): mixed
    {
        if ($field !== 'created_at') {
            return parent::cast($field, $value, $key);
        }

        try {
            return CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw $this->reject($key, __('custom.validation.date', ['attribute' => $key]));
        }
    }

    protected function applySort(Builder $query): void
    {
        $column = $this->params['sort'] ?? null;

        if (! is_string($column) || ! in_array($column, $this->sortable, true)) {
            return;
        }

        $direction = strtolower((string) ($this->params['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->reorder();

        $column === 'priority'
            ? $query->orderByRaw(self::PRIORITY_RANK . ' ' . $direction)
            : $query->orderBy('service_requests.' . $column, $direction);

        $query->orderByDesc('service_requests.id');
    }

    private function applyAssignee(Builder $query): void
    {
        $value = $this->params['assignee'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        if ($value === 'unassigned') {
            $query->whereNull('assigned_user_id');

            return;
        }

        if (! is_string($value) || ! Str::isUuid($value)) {
            throw $this->reject('assignee', __('custom.validation.uuid', ['attribute' => 'assignee']));
        }

        $query->whereHas('assignedUser', fn (Builder $q) => $q->where('uuid', $value));
    }

    private function applyRoom(Builder $query): void
    {
        $value = $this->params['room'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        if (! is_string($value)) {
            throw $this->reject('room', __('custom.validation.string', ['attribute' => 'room']));
        }

        $query->whereHas('reservation.rooms.room', fn (Builder $q) => $q->where('number', $value));
    }

    private function applyDate(Builder $query): void
    {
        $value = $this->params['date'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        try {
            [$start, $end] = HotelClock::dayWindow(is_string($value) ? $value : '');
        } catch (InvalidArgumentException) {
            throw $this->reject('date', __('custom.validation.date_format', ['attribute' => 'date', 'format' => 'Y-m-d']));
        }

        $query->where('service_requests.created_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('service_requests.created_at', '<', $end->format('Y-m-d H:i:s'));
    }

    private function applyGuest(Builder $query): void
    {
        $value = $this->params['guest'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        if (! is_string($value)) {
            throw $this->reject('guest', __('custom.validation.string', ['attribute' => 'guest']));
        }

        $value = trim($value);

        if (Str::isUuid($value)) {
            $query->whereHas('guest', fn (Builder $q) => $q->where('uuid', $value));

            return;
        }

        $query->whereHas('guest', fn (Builder $q) => $q->where(function (Builder $inner) use ($value): void {
            foreach (['name', 'first_name', 'last_name'] as $column) {
                $this->orWhereLikeInsensitive($inner, $column, $value);
            }
        }));
    }
}
