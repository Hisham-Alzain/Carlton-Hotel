<?php

namespace App\Http\Resources\Folio;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class FolioResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'reservation_uuid'      => $this->whenLoaded('reservation', fn () => $this->reservation->uuid),
            'status'                => $this->status,
            'subtotal_usd'          => $this->subtotal_usd,
            'total_usd'             => $this->total_usd,
            'approved_by_guest_at'  => $this->approved_by_guest_at?->toIso8601String(),
            'settled_at'            => $this->settled_at?->toIso8601String(),
            'items'                 => FolioItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
