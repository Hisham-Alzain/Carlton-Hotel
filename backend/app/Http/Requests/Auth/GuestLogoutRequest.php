<?php

namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;

class GuestLogoutRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Optional FCM token of this device: when it belongs to the caller,
            // the device_tokens row is removed so pushes stop reaching it.
            'device_token' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
