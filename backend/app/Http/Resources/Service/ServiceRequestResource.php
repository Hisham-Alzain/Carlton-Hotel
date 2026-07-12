<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ServiceRequestResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'type'       => $this->type,
            'department' => $this->department,
            'status'     => $this->status,
            'priority'   => $this->priority,
            'notes'      => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
