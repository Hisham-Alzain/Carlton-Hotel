<?php
namespace App\Http\Resources;

use App\Base\BaseResource;

class GuestResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'uuid'           => $this->uuid,
            'name'           => $this->name,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'phone'          => $this->phone,
            'phone_country'  => $this->phone_country,
            'phone_verified' => (bool) $this->phone_verified_at,
            'email'          => $this->email,
            'email_verified' => (bool) $this->email_verified_at,
            'preferred_locale'        => $this->preferred_locale,
            'has_active_reservation'  => $this->whenLoaded('activeReservations', fn () => $this->activeReservations->isNotEmpty(), false),
            'active_reservation'      => $this->when(
                $this->relationLoaded('activeReservations') && $this->activeReservations->isNotEmpty(),
                fn () => [
                    'uuid'         => $this->activeReservations->first()->uuid,
                    'booking_code' => $this->activeReservations->first()->booking_code,
                    'status'       => $this->activeReservations->first()->status,
                    'check_in'     => $this->activeReservations->first()->check_in,
                    'check_out'    => $this->activeReservations->first()->check_out,
                ]
            ),
        ];
    }
}
