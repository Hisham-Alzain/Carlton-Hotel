<?php

namespace App\Http\Requests\Notification;

use App\Base\BaseRequest;
use App\Enums\DevicePlatform;
use Illuminate\Validation\Rule;

class RegisterDeviceTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'token'    => ['required', 'string', 'max:500'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
        ];
    }
}
