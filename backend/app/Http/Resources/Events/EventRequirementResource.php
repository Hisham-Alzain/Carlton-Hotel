<?php

namespace App\Http\Resources\Events;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'  => $this->uuid,
            'type'  => $this->type,
            'notes' => $this->notes,
        ];
    }
}
