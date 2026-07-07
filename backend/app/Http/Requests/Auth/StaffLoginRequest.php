<?php
namespace App\Http\Requests\Auth;

use App\Base\BaseRequest;

class StaffLoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }
}
