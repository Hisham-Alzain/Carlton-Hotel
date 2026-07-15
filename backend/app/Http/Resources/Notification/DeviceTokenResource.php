<?php

namespace App\Http\Resources\Notification;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class DeviceTokenResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'platform'      => $this->platform,
            'last_used_at'  => $this->last_used_at?->toIso8601String(),
        ];
    }
}
