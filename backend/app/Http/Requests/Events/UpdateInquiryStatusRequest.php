<?php

namespace App\Http\Requests\Events;

use App\Base\BaseRequest;
use App\Enums\EventInquiryStatus;
use Illuminate\Validation\Rule;

class UpdateInquiryStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(EventInquiryStatus::class)->only(EventInquiryStatus::staffAssignable())],
        ];
    }
}
