<?php

namespace App\Http\Requests\Cms;

use App\Base\BaseRequest;
use App\Enums\BedType;
use App\Enums\RoomView;
use Illuminate\Validation\Rule;

class UpdateRoomTypeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name.en'                => ['sometimes', 'required', 'string', 'max:255'],
            'name.ar'                => ['sometimes', 'required', 'string', 'max:255'],
            'description.en'         => ['sometimes', 'required', 'string'],
            'description.ar'         => ['sometimes', 'required', 'string'],
            // Omit the key to leave the existing pivot untouched; send [] to clear.
            'amenities'              => ['nullable', 'array'],
            'amenities.*.uuid'       => ['required', 'string', 'exists:amenities,uuid'],
            'amenities.*.is_highlight' => ['boolean'],
            'amenities.*.sort_order' => ['integer', 'min:0'],
            'view_type'              => ['nullable', Rule::enum(RoomView::class)],
            'bed_types'              => ['nullable', 'array'],
            'bed_types.*'            => [Rule::enum(BedType::class)],
            'base_occupancy'         => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_occupancy'          => ['sometimes', 'integer', 'min:1', 'max:20'],
            'size_sqm'               => ['nullable', 'numeric', 'min:1'],
            'base_price_usd'         => ['sometimes', 'numeric', 'min:0'],
            'cancellation_hours'     => ['integer', 'min:0', 'max:8760'],
            'is_active'              => ['boolean'],
            'sort_order'             => ['integer', 'min:0'],
        ];
    }
}
