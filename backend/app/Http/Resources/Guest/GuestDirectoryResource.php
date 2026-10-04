<?php

namespace App\Http\Resources\Guest;

use App\Base\BaseResource;
use App\Enums\GuestStayStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Support\StayPayload;
use Illuminate\Http\Request;

/**
 * One staff directory row (D-02), over the array GuestService::index builds:
 * `['guest' => Guest, 'stay_status' => GuestStayStatus, 'current_reservation' => ?Reservation]`.
 *
 * Exactly thirteen keys. No notes, no preferences, no key: those belong to the
 * profile (or, for the key code, to nobody on the staff side).
 */
class GuestDirectoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        /** @var Guest $guest */
        $guest = $this->resource['guest'];
        /** @var GuestStayStatus $stayStatus */
        $stayStatus = $this->resource['stay_status'];
        /** @var Reservation|null $current */
        $current = $this->resource['current_reservation'];

        return [
            'uuid'                => $guest->uuid,
            'name'                => $guest->name,
            'first_name'          => $guest->first_name,
            'last_name'           => $guest->last_name,
            'phone'               => $guest->phone,
            'phone_country'       => $guest->phone_country,
            'phone_verified'      => $guest->phone_verified_at !== null,
            'email'               => $guest->email,
            'email_verified'      => $guest->email_verified_at !== null,
            'preferred_locale'    => $guest->preferred_locale,
            'stay_status'         => $stayStatus->value,
            'current_reservation' => $current ? StayPayload::summary($current) : null,
            'created_at'          => $guest->created_at?->toIso8601String(),
            // Phase 9.1 (D-12): additive — an erased account shows as `deleted`.
            'account_status'      => $guest->account_status?->value ?? 'active',
            'account_deleted_at'  => $guest->account_deleted_at?->toIso8601String(),
        ];
    }
}
