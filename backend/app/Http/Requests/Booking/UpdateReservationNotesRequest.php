<?php

namespace App\Http\Requests\Booking;

use App\Base\BaseRequest;

class UpdateReservationNotesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // The key is required (`present`); null clears the notes. Empty and
            // whitespace-only text arrive as null through the global
            // TrimStrings / ConvertEmptyStringsToNull middleware. max counts
            // characters (code points), not bytes.
            'notes' => ['present', 'nullable', 'string', 'max:2000'],
        ];
    }
}
