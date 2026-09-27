<?php

namespace App\Support;

use App\Enums\BookableType;
use App\Enums\CheckOutMode;
use App\Enums\ReservationStatus;
use App\Enums\ServiceBookingStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\ServiceBooking;
use App\Models\ServiceRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Departure services for one hotel-local day (Phase 6, SVC-02; D-18, D-19, D-21).
 *
 * A projection over existing tables — nothing is persisted. Rows carry the
 * bare uuid of their source plus `source_type` (service_booking,
 * service_request, reservation), so a status change goes straight to the
 * source's own writer (D-22).
 *
 * Departing set: reservations checked in or checked out whose `check_out` is
 * D, or whose `checked_out_at` falls in D's hotel-local day, compared as a
 * half-open UTC range from HotelClock::dayWindow() (never date() on the
 * timestamp). Kinds:
 *  - transfer: transfer bookings of the set, except arrival pickups (scheduled
 *    before D's hotel-local midnight);
 *  - late_checkout / luggage: service requests of those types on the set;
 *  - express_checkout: reservations of the set checked out by the guest
 *    (`check_out_mode = guest_express`), read-only, stage resolved.
 *
 * At most 5 set-based queries (stays + guests, bookings, requests + assignees);
 * each source is capped at LIMIT + 1 rows and the merged list at LIMIT, with
 * `truncated` telling the client there was more (exact for one source,
 * conservative across sources).
 */
final class DepartureServiceProjection
{
    public const KINDS = ['transfer', 'late_checkout', 'luggage', 'express_checkout'];

    public const REQUEST_KINDS = ['late_checkout', 'luggage'];

    public const LIMIT = 500;

    /**
     * @param  list<string>  $kinds     empty = every kind
     * @param  list<string>  $statuses  empty = every status
     * @return array{items: list<array<string, mixed>>, meta: array{count: int, truncated: bool}}
     */
    public function build(string $date, array $kinds = [], array $statuses = []): array
    {
        $kinds = $kinds === [] ? self::KINDS : $kinds;
        [$start, $end] = HotelClock::dayWindow($date);

        $stays = $this->reservationQuery()
            ->whereIn('status', [ReservationStatus::CHECKED_IN->value, ReservationStatus::CHECKED_OUT->value])
            ->where(fn (Builder $q) => $q->whereDate('check_out', $date)->orWhere(
                fn (Builder $w) => $w->where('checked_out_at', '>=', $start->format('Y-m-d H:i:s'))
                    ->where('checked_out_at', '<', $end->format('Y-m-d H:i:s')),
            ))
            ->get()
            ->keyBy('id');

        $rows = [];

        if ($stays->isNotEmpty()) {
            $ids = $stays->keys()->all();

            if (in_array('transfer', $kinds, true) && ($bookingStatuses = $this->matching($statuses, ServiceBookingStatus::values())) !== []) {
                ServiceBooking::query()
                    ->where('bookable_type', BookableType::TRANSFER->value)
                    ->whereIn('reservation_id', $ids)
                    ->where('scheduled_at', '>=', $start->format('Y-m-d H:i:s'))
                    ->when($statuses !== [], fn (Builder $q) => $q->whereIn('status', $bookingStatuses))
                    ->orderBy('scheduled_at')->orderBy('id')
                    ->limit(self::LIMIT + 1)
                    ->get()
                    ->each(function (ServiceBooking $b) use (&$rows, $stays) {
                        $rows[] = $this->row($b, $stays[$b->reservation_id]);
                    });
            }

            $requestKinds = array_values(array_intersect(self::REQUEST_KINDS, $kinds));

            if ($requestKinds !== [] && ($requestStatuses = $this->matching($statuses, ServiceRequestStatus::values())) !== []) {
                ServiceRequest::query()
                    ->with('assignedUser:id,uuid')
                    ->whereIn('type', $requestKinds)
                    ->whereIn('reservation_id', $ids)
                    ->when($statuses !== [], fn (Builder $q) => $q->whereIn('status', $requestStatuses))
                    ->orderBy('created_at')->orderBy('id')
                    ->limit(self::LIMIT + 1)
                    ->get()
                    ->each(function (ServiceRequest $r) use (&$rows, $stays) {
                        $rows[] = $this->row($r, $stays[$r->reservation_id]);
                    });
            }

            if (in_array('express_checkout', $kinds, true) && ($statuses === [] || in_array('completed', $statuses, true))) {
                $stays->filter(fn (Reservation $r) => $r->check_out_mode === CheckOutMode::GUEST_EXPRESS)
                    ->each(function (Reservation $r) use (&$rows) {
                        $rows[] = $this->row($r, $r);
                    });
            }
        }

        // scheduled_at ascending with null last, then created_at, then uuid —
        // all ISO-8601 UTC strings, so string order is time order.
        usort($rows, fn (array $a, array $b) => [$a['scheduled_at'] === null, $a['scheduled_at'], $a['created_at'], $a['uuid']]
            <=> [$b['scheduled_at'] === null, $b['scheduled_at'], $b['created_at'], $b['uuid']]);

        $truncated = count($rows) > self::LIMIT;
        $items     = array_slice($rows, 0, self::LIMIT);

        return ['items' => $items, 'meta' => ['count' => count($items), 'truncated' => $truncated]];
    }

    /**
     * One departure row. Without `$reservation` the stay is loaded here (two
     * queries), which is what a single refreshed row after a PATCH needs.
     *
     * @return array<string, mixed>
     */
    public function row(ServiceBooking|ServiceRequest|Reservation $source, ?Reservation $reservation = null): array
    {
        $reservation ??= $this->reservationQuery()
            ->whereKey($source instanceof Reservation ? $source->getKey() : $source->reservation_id)
            ->firstOrFail();

        [$kind, $sourceType, $status, $stage, $allowed, $scheduledAt, $notes, $assignee, $createdAt] = match (true) {
            $source instanceof ServiceBooking => [
                'transfer', 'service_booking', $source->status->value, $this->bookingStage($source->status),
                $this->values($source->status->allowedTargets()), $source->scheduled_at, $source->notes, null, $source->created_at,
            ],
            $source instanceof ServiceRequest => [
                $source->type, 'service_request', $source->status->value, $this->requestStage($source->status),
                $this->values($source->status->allowedTargets()), null, $source->notes,
                $source->assigned_user_id !== null ? $source->loadMissing('assignedUser')->assignedUser?->uuid : null,
                $source->created_at,
            ],
            default => [
                'express_checkout', 'reservation', 'completed', 'resolved', [], $source->checked_out_at, null, null, $source->checked_out_at,
            ],
        };

        return [
            'uuid'               => $source->uuid,
            'kind'               => $kind,
            'source_type'        => $sourceType,
            'status'             => $status,
            'stage'              => $stage,
            'allowed_statuses'   => $allowed,
            'scheduled_at'       => $this->iso($scheduledAt),
            'notes'              => $notes,
            'reservation'        => [
                'uuid'           => $reservation->uuid,
                'booking_code'   => $reservation->booking_code,
                'check_out'      => $reservation->check_out?->toDateString(),
                'checked_out_at' => $this->iso($reservation->checked_out_at),
                'status'         => $reservation->status?->value,
            ],
            'guest'              => $reservation->guest ? [
                'uuid'  => $reservation->guest->uuid,
                'name'  => $reservation->guest->name,
                'phone' => $reservation->guest->phone,
            ] : null,
            'room_number'        => $reservation->room_number !== null ? (string) $reservation->room_number : null,
            'assigned_user_uuid' => $assignee,
            'created_at'         => $this->iso($createdAt),
        ];
    }

    /** Stays with their guest and first assigned room number (one query + one eager load). */
    private function reservationQuery(): Builder
    {
        return Reservation::query()
            ->select(['reservations.id', 'reservations.uuid', 'reservations.guest_id', 'reservations.booking_code',
                'reservations.check_out', 'reservations.checked_out_at', 'reservations.status', 'reservations.check_out_mode'])
            ->addSelect(['room_number' => Room::withTrashed()
                ->select('rooms.number')
                ->join('reservation_rooms', 'reservation_rooms.room_id', '=', 'rooms.id')
                ->whereColumn('reservation_rooms.reservation_id', 'reservations.id')
                ->orderBy('reservation_rooms.id')
                ->limit(1),
            ])
            ->with('guest:id,uuid,name,phone');
    }

    private function bookingStage(ServiceBookingStatus $status): string
    {
        return match ($status) {
            ServiceBookingStatus::PENDING   => 'open',
            ServiceBookingStatus::CONFIRMED => 'in_progress',
            default                         => 'resolved',
        };
    }

    private function requestStage(ServiceRequestStatus $status): string
    {
        return match ($status) {
            ServiceRequestStatus::NEW         => 'open',
            ServiceRequestStatus::IN_PROGRESS => 'in_progress',
            default                           => 'resolved',
        };
    }

    /**
     * The requested statuses that belong to one family; with no status filter,
     * every value of the family.
     *
     * @param  list<string>  $statuses
     * @param  list<string>  $family
     * @return list<string>
     */
    private function matching(array $statuses, array $family): array
    {
        return $statuses === [] ? $family : array_values(array_intersect($family, $statuses));
    }

    /** @param list<\BackedEnum> $cases */
    private function values(array $cases): array
    {
        return array_map(static fn (\BackedEnum $c) => $c->value, $cases);
    }

    private function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->utc()->toIso8601String();
    }
}
