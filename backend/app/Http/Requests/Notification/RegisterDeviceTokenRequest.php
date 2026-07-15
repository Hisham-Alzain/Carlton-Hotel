<?php

namespace App\Http\Requests\Notification;

use App\Base\BaseRequest;

class RegisterDeviceTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'token'    => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'in:ios,android,web'],
        ];
    }
}
