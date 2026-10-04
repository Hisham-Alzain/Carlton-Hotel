<?php

namespace App\Http\Resources\Guest;

use App\Base\BaseResource;
use App\Enums\GuestStayStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Support\PreArrivalChecklist;
use App\Support\StayPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The staff guest profile (Phase 4, D-04), over the array
 * GuestService::profile builds. Staff-only: referenced by Admin\GuestController
 * alone and never reused on a guest route.
 *
 * Composed entirely from the shared builders (StayPayload, PreArrivalChecklist,
 * GuestPreferencesResource, GuestNoteResource) — no hand-rolled reservation
 * shape, no document path, never the key code. Runs no queries.
 */
class GuestProfileResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        /** @var Guest $guest */
        $guest = $this->resource['guest'];
        /** @var GuestStayStatus $stayStatus */
        $stayStatus = $this->resource['stay_status'];
        /** @var Reservation|null $target */
        $target = $this->resource['current_reservation'];
        /** @var Collection<int, Reservation> $history */
        $history = $this->resource['history'];

        return [
            'uuid'                  => $guest->uuid,
            'name'                  => $guest->name,
            'first_name'            => $guest->first_name,
            'last_name'             => $guest->last_name,
            'phone'                 => $guest->phone,
            'phone_country'         => $guest->phone_country,
            'phone_verified'        => $guest->phone_verified_at !== null,
            'email'                 => $guest->email,
            'email_verified'        => $guest->email_verified_at !== null,
            'preferred_locale'      => $guest->preferred_locale,
            'created_at'            => $guest->created_at?->toIso8601String(),
            'stay_status'           => $stayStatus->value,
            'stats'                 => $this->resource['stats'],
            'preferences'           => (new GuestPreferencesResource($guest))->resolve($request),
            'current_reservation'   => $target ? StayPayload::staffReservation($target) : null,
            'pre_arrival_checklist' => $target ? PreArrivalChecklist::for($target) : null,
            'stay_history'          => $history->map(fn (Reservation $r) => StayPayload::historyItem($r))->values()->all(),
            'stays_total'           => $this->resource['stays_total'],
            'has_more'              => $this->resource['has_more'],
            'notes'                 => GuestNoteResource::collection($this->resource['notes'])->resolve($request),
            'notes_count'           => $this->resource['notes_count'],
            // Phase 9.1 (D-12): additive — an erased account shows as `deleted`.
            'account_status'        => $guest->account_status?->value ?? 'active',
            'account_deleted_at'    => $guest->account_deleted_at?->toIso8601String(),
        ];
    }
}
