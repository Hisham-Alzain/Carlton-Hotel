<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;

/** Phase 8 (D-09): `done` is explicit — never a blind toggle. */
class UpdateEventChecklistItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'done' => ['required', 'boolean'],
        ];
    }
}
