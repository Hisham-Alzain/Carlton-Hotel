<?php

namespace App\Filters;

use App\Base\BaseFilter;
use App\Enums\ServiceRequestStatus;

/**
 * The guest's own service-request list: `status` only (eq / in).
 *
 * Unlike the staff board (ServiceRequestFilter, where an unknown status simply
 * matches nothing) an unknown value here is a 422 (BaseFilter rule 3): the app
 * has no free-text filter, and a typo must not read as "you have no requests".
 * Staff-board params (assignee, guest, room, department, sort) are deliberately
 * not whitelisted. The guest scope is applied by the service, never by a param.
 */
class GuestServiceRequestFilter extends BaseFilter
{
    protected array $safeParms = [
        'status' => ['eq', 'in'],
    ];

    protected function cast(string $field, mixed $value, string $key): mixed
    {
        if ($field === 'status' && ! in_array($value, ServiceRequestStatus::values(), true)) {
            throw $this->reject($key, __('custom.validation.in', ['attribute' => $key]));
        }

        return parent::cast($field, $value, $key);
    }
}
