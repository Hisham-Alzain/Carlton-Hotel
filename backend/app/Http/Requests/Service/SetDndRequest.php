<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class SetDndRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            // Omit to default to the end of the current hotel day.
            'until'   => ['nullable', 'date', 'after:now'],
        ];
    }
}
