<?php

namespace App\Http\Requests\Folio;

use App\Base\BaseRequest;

/**
 * PATCH /cms/folios/{folio}/line-items/{item}/dispute (D-11): staff raise a
 * dispute (with a reason) or close the open one as resolved/rejected (with a
 * note the guest can read).
 */
class StaffFolioDisputeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:raise,resolve,reject'],
            'reason' => ['required_if:action,raise', 'nullable', 'string', 'max:500'],
            'note'   => ['required_if:action,resolve,reject', 'nullable', 'string', 'max:1000'],
        ];
    }
}
