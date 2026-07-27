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
            // Null for legacy free-string requests placed outside the catalog.
            'service_item' => $this->whenLoaded('serviceItem', fn () => $this->serviceItem
                ? new ServiceItemResource($this->serviceItem)
                : null),
            'category_code' => $this->whenLoaded('serviceItem', fn () => $this->serviceItem?->category?->code),
        ];
    }
}
