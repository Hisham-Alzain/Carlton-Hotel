<?php

namespace App\Filters;

use App\Base\BaseFilter;
use App\Enums\ServiceRequestPriority;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Filters for `GET /support-tickets` (Phase 7, D-12, D-13).
 *
 * DSL fields: `status`, `department`, `source`, `category` (eq, in) — an
 * unknown value matches nothing; `priority` (eq, in) takes the labels
 * low|normal|high and is mapped onto the stored 1-3 scale (an unknown label
 * matches nothing); `created_at` (gte, lte) parsed as an instant in UTC.
 *
 * Custom params, each blank = no filter:
 *  - `assignee`: a staff uuid or `unassigned`. `me` never reaches this class —
 *    the controller swaps it for the caller's uuid; this filter reads no auth;
 *  - `guest`, `reservation`: a uuid;
 *  - `escalated`: boolean — true = escalation_level > 0, false = 0.
 * Anything structurally malformed is a 422 (BaseFilter rule 3).
 *
 * Sort: created_at, updated_at, priority, status, with `id` descending as the
 * tie-break. The default (created_at desc, id desc) is applied here too.
 */
class TicketFilter extends BaseFilter
{
    protected array $safeParms = [
        'status'     => ['eq', 'in'],
        'department' => ['eq', 'in'],
        'source'     => ['eq', 'in'],
        'category'   => ['eq', 'in'],
        'priority'   => ['eq', 'in'],
        'created_at' => ['gte', 'lte'],
    ];

    protected array $sortable = ['created_at', 'updated_at', 'priority', 'status'];

    /** A priority no row holds, so an unknown label matches nothing. */
    private const NO_PRIORITY = -1;

    protected function applyConditions(Builder $query): void
    {
        parent::applyConditions($query);

        $this->applyAssignee($query);
        $this->applyUuidLink($query, 'guest', 'guest_id', Guest::class);
        $this->applyUuidLink($query, 'reservation', 'reservation_id', Reservation::class);
        $this->applyEscalated($query);
    }

    protected function cast(string $field, mixed $value, string $key): mixed
    {
        return match ($field) {
            'priority'   => ServiceRequestPriority::tryFrom((string) $value)?->toTicketScale() ?? self::NO_PRIORITY,
            'created_at' => $this->castInstant($value, $key),
            default      => parent::cast($field, $value, $key),
        };
    }

    protected function applySort(Builder $query): void
    {
        $column = $this->params['sort'] ?? null;

        $query->reorder();

        if (is_string($column) && in_array($column, $this->sortable, true)) {
            $direction = strtolower((string) ($this->params['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy('tickets.' . $column, $direction);
        } else {
            $query->orderByDesc('tickets.created_at');
        }

        $query->orderByDesc('tickets.id');
    }

    private function castInstant(mixed $value, string $key): string
    {
        try {
            return CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw $this->reject($key, __('custom.validation.date', ['attribute' => $key]));
        }
    }

    private function applyAssignee(Builder $query): void
    {
        $value = $this->params['assignee'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        if ($value === 'unassigned') {
            $query->whereNull('tickets.assigned_user_id');

            return;
        }

        if (! is_string($value) || ! Str::isUuid($value)) {
            throw $this->reject('assignee', __('custom.validation.uuid', ['attribute' => 'assignee']));
        }

        $query->whereIn('tickets.assigned_user_id', User::query()->select('id')->where('uuid', $value));
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function applyUuidLink(Builder $query, string $param, string $column, string $model): void
    {
        $value = $this->params[$param] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        if (! is_string($value) || ! Str::isUuid($value)) {
            throw $this->reject($param, __('custom.validation.uuid', ['attribute' => $param]));
        }

        $query->whereIn('tickets.' . $column, $model::query()->select('id')->where('uuid', $value));
    }

    private function applyEscalated(Builder $query): void
    {
        $value = $this->params['escalated'] ?? null;

        if ($this->isBlank($value)) {
            return;
        }

        $this->castBool($this->scalar($value, 'escalated'), 'escalated')
            ? $query->where('tickets.escalation_level', '>', 0)
            : $query->where('tickets.escalation_level', 0);
    }
}
