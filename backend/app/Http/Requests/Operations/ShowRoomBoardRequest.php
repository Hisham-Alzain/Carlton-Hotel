<?php

namespace App\Http\Requests\Operations;

use App\Base\BaseRequest;
use App\Enums\RoomStatus;
use Illuminate\Validation\Rule;

class ShowRoomBoardRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'date'      => ['nullable', 'date_format:Y-m-d'],
            'status'    => ['nullable', Rule::enum(RoomStatus::class)],
            'floor'     => ['nullable', 'integer', 'min:0', 'max:200'],
            'room_type' => ['nullable', 'string', 'max:36'],
        ];
    }
}
