<?php

namespace App\Http\Resources\Events;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventInquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'            => $this->uuid,
            'name'            => $this->name,
            'email'           => $this->email,
            'phone'           => $this->phone,
            'company'         => $this->company,
            'event_type'      => $this->event_type,
            'event_date'      => $this->event_date?->toDateString(),
            'expected_guests' => $this->expected_guests,
            'budget_usd'      => $this->budget_usd,
            'notes'           => $this->notes,
            'status'          => $this->status,
            'department'      => $this->department,
            'assigned_to'     => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->uuid),
            'requirements'    => $this->whenLoaded('requirements', fn () => EventRequirementResource::collection($this->requirements)),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
