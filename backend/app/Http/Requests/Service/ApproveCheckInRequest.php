<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;
use App\Enums\CheckInApprovalStatus;
use Illuminate\Validation\Rule;

class ApproveCheckInRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CheckInApprovalStatus::class)->only(CheckInApprovalStatus::decisions())],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
