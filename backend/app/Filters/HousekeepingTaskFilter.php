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
 * Filters for `GET /housekeeping/tasks` (Phase 6, HK-01).
 *
 * DSL fields: `status`, `type`, `priority` (eq, in) — like every enum column
 * filter an unknown value matches nothing; `due_at` (gte, lte), parsed as an
 * instant and compared in UTC, inclusive at the exact instant.
 *
 * Custom params, each blank = no filter:
 *  - `room`: a room number or a room uuid;
 *  - `assignee`: a staff uuid or `unassigned`; anything else is a 422;
 *  - `due_date`: `Y-m-d`, the hotel-local day [00:00, next 00:00) converted to
 *    UTC by `HotelClock::dayWindow()`; anything else is a 422.
 *
 * Sort: `due_at`, `created_at`, `priority` (by rank high > normal > low, not
 * alphabetically), always with `id` ascending as the tiebreak. The default
 * order (due_at ascending, nulls last) lives in HousekeepingTaskService.
 */
class HousekeepingTaskFilter extends BaseFilter
{
    protected array $safeParms = [
        'status'   => ['eq', 'in'],
        'type'     => ['eq', 'in'],
        'priority' => ['eq', 'in'],
        'due_at'   => ['gte', 'lte'],
    ];

    protected array $sortable = ['due_at', 'created_at', 'priority'];

    private const PRIORITY_RANK = "case priority when 'high' then 3 when 'normal' then 2 when 'low' then 1 else 0 end";

    protected function applyConditions(Builder $query): void
    {
        parent::applyConditions($query);

        $this->applyRoom($query);
        $this->applyAssignee($query);
        $this->applyDueDate($query);
    }

    /** `due_at` bounds are instants: parse and compare in UTC, in the stored format. */
    protected function cast(string $field, mixed $value, string $key): mixed
    {
        if ($field !== 'due_at') {
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

        if ($column !== 'priority') {
            parent::applySort($query);

            return;
        }

        $direction = strtolower((string) ($this->params['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->reorder()
            ->orderByRaw(self::PRIORITY_RANK . ' ' . $direction)
            ->orderBy($query->getModel()->getKeyName());
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

        $query->whereHas('room', fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner->where('number', $value)->orWhere('uuid', $value),
        ));
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

    private function applyDueDate(Builder $query): void
    {
        $value = $this->params['due_date'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        try {
            [$start, $end] = HotelClock::dayWindow(is_string($value) ? $value : '');
        } catch (InvalidArgumentException) {
            throw $this->reject('due_date', __('custom.validation.date_format', ['attribute' => 'due_date', 'format' => 'Y-m-d']));
        }

        $query->where('due_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('due_at', '<', $end->format('Y-m-d H:i:s'));
    }
}
