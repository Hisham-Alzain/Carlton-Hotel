<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;

/**
 * Shared by the availability and rates grids (D-08): `from` defaults to today
 * and may reach back at most 365 days (audit views); `days` defaults to 14 in
 * the service and is capped at 31 so the window cannot be used to load the
 * database.
 */
class ShowFrontDeskGridRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now()->subDays(365)->toDateString()],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
