<?php

namespace App\Http\Requests\Guest;

use App\Base\BaseRequest;

class AddGuestNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Whitespace-only text arrives as null through the global
            // TrimStrings / ConvertEmptyStringsToNull middleware, so `required`
            // rejects it. max counts characters (code points), not bytes.
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
