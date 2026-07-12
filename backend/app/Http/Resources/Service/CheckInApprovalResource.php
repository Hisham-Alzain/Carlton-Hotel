<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class CheckInApprovalResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'reservation_uuid'  => $this->whenLoaded('reservation', fn () => $this->reservation->uuid),
            'status'            => $this->status,
            'approved_by'       => $this->whenLoaded('approver', fn () => $this->approver?->uuid),
            'notes'             => $this->notes,
            'documents'         => $this->whenLoaded('reservation', fn () => GuestDocumentResource::collection(
                $this->reservation->relationLoaded('documents') ? $this->reservation->documents : collect()
            )),
        ];
    }
}
