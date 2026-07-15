<?php

namespace App\Http\Resources\Chat;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class MessageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'            => $this->uuid,
            'sender_type'     => $this->resource->senderLabel(),
            'body'            => $this->body,
            'attachment_url'  => $this->attachment_url,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
