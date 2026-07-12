<?php

namespace App\Http\Resources\Service;

use App\Base\BaseResource;
use Illuminate\Http\Request;

class ServiceBookingResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'bookable_type' => $this->bookable_type,
            'bookable'      => $this->whenLoaded('bookable', fn () => [
                'uuid'  => $this->bookable->uuid,
                'label' => method_exists($this->bookable, 'getTranslation')
                    ? $this->bookable->getTranslation('name', $request->getLocale())
                    : $this->bookable->table_number,
            ]),
            'scheduled_at'  => $this->scheduled_at,
            'status'        => $this->status,
            'notes'         => $this->notes,
        ];
    }
}
