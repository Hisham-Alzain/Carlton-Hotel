<?php
namespace App\Http\Resources;

use App\Base\BaseResource;
use App\Models\Reservation;

class GuestResource extends BaseResource
{
    public function toArray($request): array
    {
        $loaded  = $this->relationLoaded('activeReservations');
        $today   = now()->startOfDay();
        $booked  = $loaded
            ? $this->activeReservations
                ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
                ->filter(fn ($r) => $r->check_out->gte($today))
            : collect();
        $current = $booked->sortByDesc('check_in')->first();

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
            // Two-flag entitlement (ARCHITECTURE §3.7): has_booking unlocks pre-arrival tier, is_checked_in unlocks in-room tier.
            'has_booking'             => $booked->isNotEmpty(),
            'is_checked_in'           => $booked->contains(fn ($r) => $r->status === Reservation::STATUS_CHECKED_IN),
            // Deprecated alias, kept for one release — API_GUIDE_MOBILE.md documents the app reading this field. Equals has_booking.
            'has_active_reservation'  => $booked->isNotEmpty(),
            'active_reservation'      => $current ? [
                'uuid'         => $current->uuid,
                'booking_code' => $current->booking_code,
                'status'       => $current->status,
                'check_in'     => $current->check_in,
                'check_out'    => $current->check_out,
            ] : null,
        ];
    }
}
