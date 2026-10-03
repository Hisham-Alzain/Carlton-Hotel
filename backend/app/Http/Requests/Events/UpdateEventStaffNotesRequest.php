<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;

/** Phase 8 (D-01): internal notes; `null` clears them. The guest's `notes` is not writable here. */
class UpdateEventStaffNotesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'staff_notes' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }
}
