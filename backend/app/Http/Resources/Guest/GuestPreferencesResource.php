<?php

namespace App\Http\Resources\Guest;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/** A guest's preferences (D-08/D-09), wrapping a Guest model. */
class GuestPreferencesResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'bed_type'         => $this->bed_type?->value,
            'pillow_type'      => $this->pillow_type?->value,
            'floor_preference' => $this->floor_preference?->value,
            'other'            => $this->preferences_other,
            'updated_at'       => $this->preferences_updated_at?->toIso8601String(),
        ];
    }
}
