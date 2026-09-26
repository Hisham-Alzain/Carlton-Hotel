<?php

namespace App\Support;

use App\Enums\CheckInApprovalStatus;
use App\Models\Reservation;
use LogicException;

/**
 * The pre-arrival checklist (Phase 4, D-05): derived at read time from already
 * loaded relations, never stored and never queried for.
 *
 * Six items in a fixed order, each `{key, done, <detail>}`; `complete` is true
 * only when all six are done. The same builder feeds the guest stay screens
 * (via StayPayload::forGuest) and the staff profile, so the two can never
 * disagree about what is outstanding.
 */
final class PreArrivalChecklist
{
    /** Relations the checklist reads; each must be eager-loaded by the caller. */
    private const RELATIONS = ['rooms', 'checkInApproval', 'documents', 'guest'];

    public static function for(Reservation $reservation): array
    {
        self::assertLoaded($reservation);

        $approvalStatus = $reservation->checkInApproval?->status;
        $guest          = $reservation->guest;
        $arrivalTime    = StayPayload::arrivalTime($reservation);
        $roomNumber     = $reservation->rooms->first()?->room?->number;
        $keyActive      = $reservation->hasActiveDigitalKey();

        $items = [
            [
                'key'   => 'documents_uploaded',
                'done'  => $reservation->documents->isNotEmpty(),
                'count' => $reservation->documents->count(),
            ],
            [
                'key'    => 'check_in_approved',
                'done'   => $approvalStatus === CheckInApprovalStatus::APPROVED,
                'status' => $approvalStatus?->value,
            ],
            [
                // `any` counts as a positive choice; the free-text note alone does not.
                'key'  => 'preferences_set',
                'done' => $guest !== null && (
                    $guest->bed_type !== null
                    || $guest->pillow_type !== null
                    || $guest->floor_preference !== null
                ),
            ],
            [
                'key'          => 'arrival_time_set',
                'done'         => $arrivalTime !== null,
                'arrival_time' => $arrivalTime,
            ],
            [
                'key'         => 'room_assigned',
                'done'        => $roomNumber !== null,
                'room_number' => $roomNumber,
            ],
            [
                'key'        => 'digital_key_issued',
                'done'       => $keyActive,
                'expires_at' => $keyActive ? $reservation->digital_key_expires_at->toIso8601String() : null,
            ],
        ];

        return [
            'reservation_uuid' => $reservation->uuid,
            'complete'         => collect($items)->every(fn (array $item) => $item['done']),
            'items'            => $items,
        ];
    }

    /**
     * A missing eager load fails loudly here instead of lazy-loading one query
     * per stay in a production list (FA-4.03-4).
     */
    private static function assertLoaded(Reservation $reservation): void
    {
        foreach (self::RELATIONS as $relation) {
            if (! $reservation->relationLoaded($relation)) {
                throw new LogicException("PreArrivalChecklist needs the [{$relation}] relation eager-loaded on Reservation.");
            }
        }

        $line = $reservation->rooms->first();

        if ($line !== null && $line->room_id !== null && ! $line->relationLoaded('room')) {
            throw new LogicException('PreArrivalChecklist needs the [rooms.room] relation eager-loaded on Reservation.');
        }
    }
}
