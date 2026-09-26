<?php

namespace App\Http\Resources\Guest;

use App\Base\BaseResource;
use Illuminate\Http\Request;

/** Staff-only. No numeric key and no foreign-key column leaves the API. */
class GuestNoteResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $author = $this->relationLoaded('author') ? $this->author : null;

        return [
            'uuid'       => $this->uuid,
            'body'       => $this->body,
            'author'     => $author ? ['uuid' => $author->uuid, 'name' => $author->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
