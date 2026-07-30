<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Support\TranslatableRules;

/**
 * Editor metadata on an existing asset. No `image`, no `path`, no `mediable_*`:
 * replacing a file is a new upload, and moving an asset between parents is
 * attach + delete, both of which leave an audit trail this route would not.
 */
class UpdateMediaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            ...TranslatableRules::optional('alt_text', ['string', 'max:255']),
            'title'      => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
