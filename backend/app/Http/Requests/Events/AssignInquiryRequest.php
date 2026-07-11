<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;

class AssignInquiryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'exists:users,uuid'],
        ];
    }
}
