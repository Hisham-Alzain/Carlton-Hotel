<?php

namespace App\Http\Resources\Review;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ReviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'rating'           => $this->rating,
            'comment'          => $this->comment,
            'is_verified_stay' => $this->is_verified_stay,
            'is_published'     => $this->is_published,
            'created_at'       => $this->created_at?->toIso8601String(),
            // Public reviews show a display name only — never contact details.
            'author'           => $this->whenLoaded('guest', fn () => [
                'first_name' => $this->guest->first_name,
                'last_name'  => $this->guest->last_name,
            ]),
        ];
    }
}
