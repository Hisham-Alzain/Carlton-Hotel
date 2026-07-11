<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;

class UpdateInquiryStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:in_review,quoted,confirmed,cancelled'],
        ];
    }
}
