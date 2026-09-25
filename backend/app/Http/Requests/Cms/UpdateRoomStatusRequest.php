<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Enums\RoomStatus;
use Illuminate\Validation\Rule;

class UpdateRoomStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
