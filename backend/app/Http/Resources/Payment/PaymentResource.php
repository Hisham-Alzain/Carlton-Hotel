<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'method'      => $this->method,
            'amount_usd'  => $this->amount_usd,
            'status'      => $this->status,
            'note'        => $this->note,
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder->uuid),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
