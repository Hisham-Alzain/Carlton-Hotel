<?php

namespace App\Filters;

use App\Base\BaseFilter;
use App\Enums\FolioStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters for `GET /cms/reservations` (D-10).
 *
 * `BaseFilter` rather than `CmsContentFilter`: a reservation has no
 * `is_active` column. `status` goes through the generic DSL (`?status=`,
 * `?status[in]=a,b`, `?status[]=a&status[]=b`); like every plain-column
 * filter its values are not checked against the enum, so an unknown status
 * simply matches nothing.
 *
 * `folio_status` has no column on `reservations`, so it is handled here
 * through the folio relation rather than the DSL: `open` or `settled`, blank
 * means no filter, anything else is a 422 (the three BaseFilter rules). A
 * reservation without a folio matches neither value.
 * `?status=checked_out&folio_status=open` is how the desk and the night audit
 * find the stays that left with an open folio (forced and guest express
 * check-outs).
 *
 * `has_open_disputes` (Phase 5, D-12) is `1` or `0` on the same three rules: a
 * flag for the desk and the night audit, never a gate. `0` includes
 * reservations without a folio. It combines with `folio_status`.
 */
class ReservationFilter extends BaseFilter
{
    protected array $safeParms = [
        'status' => ['eq', 'in'],
    ];

    protected function applyConditions(Builder $query): void
    {
        parent::applyConditions($query);

        $this->applyFolioStatus($query);
        $this->applyOpenDisputes($query);
    }

    private function applyFolioStatus(Builder $query): void
    {
        if (! array_key_exists('folio_status', $this->params)) {
            return;
        }

        $value = $this->params['folio_status'];

        if ($this->isBlank($value)) {
            return;
        }

        if (! is_string($value) || ! in_array($value, FolioStatus::values(), true)) {
            throw $this->reject('folio_status', __('custom.validation.in', ['attribute' => 'folio_status']));
        }

        $query->whereHas('folio', fn (Builder $q) => $q->where('status', $value));
    }

    /** Phase 5 (D-12): `1` = the folio has an open dispute; `0` = everything else, folio-less stays included. */
    private function applyOpenDisputes(Builder $query): void
    {
        if (! array_key_exists('has_open_disputes', $this->params)) {
            return;
        }

        $value = $this->params['has_open_disputes'];

        if ($this->isBlank($value)) {
            return;
        }

        if (! is_string($value) || ! in_array($value, ['1', '0'], true)) {
            throw $this->reject('has_open_disputes', __('custom.validation.in', ['attribute' => 'has_open_disputes']));
        }

        $value === '1'
            ? $query->whereHas('folio.openDisputes')
            : $query->whereDoesntHave('folio.openDisputes');
    }
}
