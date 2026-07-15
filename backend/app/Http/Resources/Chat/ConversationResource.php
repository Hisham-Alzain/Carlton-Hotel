<?php

namespace App\Http\Resources\Chat;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ConversationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'status'           => $this->status,
            'guest'            => $this->whenLoaded('guest', fn () => [
                'uuid' => $this->guest->uuid,
                'name' => $this->guest->name,
            ]),
            'assigned_user_uuid' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->uuid),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
