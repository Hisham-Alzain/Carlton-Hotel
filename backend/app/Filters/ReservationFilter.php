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
 */
class ReservationFilter extends BaseFilter
{
    protected array $safeParms = [
        'status' => ['eq', 'in'],
    ];

    protected function applyConditions(Builder $query): void
    {
        parent::applyConditions($query);

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
}
