<?php

namespace App\Support;

use App\Models\Reservation;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The one builder for the Phase 4 stay blocks (D-12): online check-in state,
 * the digital key and the pre-arrival checklist.
 *
 * Static and query-free: it reads only relations the caller eager-loaded and
 * throws a LogicException naming any it needs but finds missing.
 *
 * The guest shape (`guestDigitalKey`) is the only place in the codebase that
 * ever puts the key code into a response, and it is reached only from the
 * guest's own stay resources.
 */
final class StayPayload
{
    /** The three blocks every guest stay resource carries. */
    public static function forGuest(Reservation $reservation): array
    {
        return [
            'online_check_in'       => self::onlineCheckIn($reservation),
            'digital_key'           => self::guestDigitalKey($reservation),
            'pre_arrival_checklist' => PreArrivalChecklist::for($reservation),
        ];
    }

    public static function onlineCheckIn(Reservation $reservation): array
    {
        self::assertLoaded($reservation, 'checkInApproval');

        return [
            'arrival_time'    => self::arrivalTime($reservation),
            'submitted_at'    => $reservation->online_check_in_submitted_at?->toIso8601String(),
            'approval_status' => $reservation->checkInApproval?->status?->value,
        ];
    }

    /**
     * The stored TIME as hotel-local `H:i`. MySQL hands back `18:30:00`, so the
     * seconds are cut rather than trusted to be absent.
     */
    public static function arrivalTime(Reservation $reservation): ?string
    {
        $value = $reservation->arrival_time;

        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 5);
    }

    /**
     * `{code, issued_at, expires_at}` while the key is active, else null.
     *
     * A stored code that cannot be decrypted — APP_KEY rotated without the old
     * key in APP_PREVIOUS_KEYS — reads as "no key", never a 500 (D-11).
     */
    public static function guestDigitalKey(Reservation $reservation): ?array
    {
        if (! $reservation->hasActiveDigitalKey()) {
            return null;
        }

        try {
            $code = $reservation->digital_key_code;
        } catch (DecryptException) {
            return null;
        }

        if ($code === null || $code === '') {
            return null;
        }

        return [
            'code'       => $code,
            'issued_at'  => $reservation->digital_key_issued_at->toIso8601String(),
            'expires_at' => $reservation->digital_key_expires_at->toIso8601String(),
        ];
    }

    /**
     * The one-line reservation summary on a staff directory row (D-02):
     * `{uuid, booking_code, status, check_in, check_out, room_number}`.
     */
    public static function summary(Reservation $reservation): array
    {
        self::assertLoaded($reservation, 'rooms');

        return [
            'uuid'         => $reservation->uuid,
            'booking_code' => $reservation->booking_code,
            'status'       => $reservation->status->value,
            'check_in'     => $reservation->check_in?->toDateString(),
            'check_out'    => $reservation->check_out?->toDateString(),
            'room_number'  => $reservation->rooms->first()?->room?->number,
        ];
    }

    // ── Staff shapes (Phase 4, D-04). None of them ever reads the key code or
    //    a document's stored path. ─────────────────────────────────────────

    /**
     * The key's status for staff: `{issued_at, expires_at, revoked_at, active}`,
     * or null when no key was ever issued. Never the code (D-04, D-11).
     */
    public static function staffDigitalKey(Reservation $reservation): ?array
    {
        if ($reservation->digital_key_issued_at === null) {
            return null;
        }

        return [
            'issued_at'  => $reservation->digital_key_issued_at->toIso8601String(),
            'expires_at' => $reservation->digital_key_expires_at?->toIso8601String(),
            'revoked_at' => $reservation->digital_key_revoked_at?->toIso8601String(),
            'active'     => $reservation->hasActiveDigitalKey(),
        ];
    }

    /** Document metadata only — `{uuid, type, created_at}`; no path, no URL (D-04). */
    public static function documents(Reservation $reservation): array
    {
        self::assertLoaded($reservation, 'documents');

        return $reservation->documents
            ->map(fn ($document) => [
                'uuid'       => $document->uuid,
                'type'       => $document->type,
                'created_at' => $document->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** The staff profile's `current_reservation` (D-04). */
    public static function staffReservation(Reservation $reservation): array
    {
        self::assertLoaded($reservation, 'rooms');
        self::assertLoaded($reservation, 'checkInApproval');

        $line     = $reservation->rooms->first();
        $room     = $line?->room;
        $roomType = $line?->roomType;
        $approval = $reservation->checkInApproval;

        if ($approval !== null) {
            self::assertLoaded($approval, 'approver');
        }

        return [
            'uuid'                         => $reservation->uuid,
            'booking_code'                 => $reservation->booking_code,
            'status'                       => $reservation->status->value,
            'check_in'                     => $reservation->check_in?->toDateString(),
            'check_out'                    => $reservation->check_out?->toDateString(),
            'checked_in_at'                => $reservation->checked_in_at?->toIso8601String(),
            'arrival_time'                 => self::arrivalTime($reservation),
            'online_check_in_submitted_at' => $reservation->online_check_in_submitted_at?->toIso8601String(),
            'room'                         => $room ? [
                'uuid'   => $room->uuid,
                'number' => $room->number,
                'floor'  => $room->floor,
            ] : null,
            'room_type'                    => $roomType ? [
                'uuid' => $roomType->uuid,
                'name' => $roomType->getTranslations('name'),
            ] : null,
            'check_in_approval'            => $approval ? [
                'uuid'        => $approval->uuid,
                'status'      => $approval->status?->value,
                'notes'       => $approval->notes,
                'approved_by' => $approval->approver ? [
                    'uuid' => $approval->approver->uuid,
                    'name' => $approval->approver->name,
                ] : null,
                'updated_at'  => $approval->updated_at?->toIso8601String(),
            ] : null,
            'documents'                    => self::documents($reservation),
            'digital_key'                  => self::staffDigitalKey($reservation),
        ];
    }

    /** One stay-history item — the shape of the future paginated `/guests/{guest}/stays`. */
    public static function historyItem(Reservation $reservation): array
    {
        self::assertLoaded($reservation, 'rooms');

        $line     = $reservation->rooms->first();
        $roomType = $line?->roomType;

        return [
            'uuid'           => $reservation->uuid,
            'booking_code'   => $reservation->booking_code,
            'status'         => $reservation->status->value,
            'check_in'       => $reservation->check_in?->toDateString(),
            'check_out'      => $reservation->check_out?->toDateString(),
            'nights'         => $reservation->nights(),
            'room_number'    => $line?->room?->number,
            'room_type'      => $roomType ? [
                'uuid' => $roomType->uuid,
                'name' => $roomType->getTranslations('name'),
            ] : null,
            'total_usd'      => $reservation->total_usd,
            'checked_in_at'  => $reservation->checked_in_at?->toIso8601String(),
            'checked_out_at' => $reservation->checked_out_at?->toIso8601String(),
        ];
    }

    private static function assertLoaded(Model $model, string $relation): void
    {
        if (! $model->relationLoaded($relation)) {
            $class = class_basename($model);
            throw new LogicException("StayPayload needs the [{$relation}] relation eager-loaded on {$class}.");
        }
    }
}
