<?php

namespace App\Support;

use App\Enums\FolioDisputeStatus;
use App\Enums\FolioStatus;
use App\Enums\NightAuditCheckType;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\TicketStatus;
use App\Models\FolioItemDispute;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Ticket;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * The five night-audit check evaluators (Phase 9, D-10). Read-only: it never
 * writes a row.
 *
 * Each category runs exactly two statements — an exact COUNT and a LIMIT-20
 * evidence sample in a stable order — so the query count is the same with 0
 * or 10 000 issues (D-22). Evidence carries public identifiers only: no names,
 * phones, complaint text or dispute reasons.
 *
 * Departures/arrivals are scoped to the business date with `whereDate()`
 * (SQLite stores `date` casts as `Y-m-d 00:00:00`). Dirty rooms, high-priority
 * tickets and open disputes are **current state** at evaluation time, even
 * for an old business date (D-09). Boundaries: dirty rooms read
 * `rooms.status`, not housekeeping tasks (Phase 6); tickets ignore service
 * recoveries (Phase 7).
 */
final class NightAuditEvaluator
{
    public const EVIDENCE_LIMIT = 20;

    /**
     * Ticket priority at or above which a ticket is "high"; matches
     * `ServiceRequestPriority::fromTicketScale()` (>= 3 → HIGH).
     */
    private const HIGH_PRIORITY = 3;

    /**
     * @return array<string, array{issue_count: int, evidence: list<array<string, string>>, evidence_truncated: bool}>
     *         keyed by NightAuditCheckType value, in enum order
     */
    public function evaluate(string $businessDate): array
    {
        $result = [];

        foreach (NightAuditCheckType::cases() as $type) {
            $result[$type->value] = match ($type) {
                NightAuditCheckType::UNSETTLED_DEPARTURES       => $this->unsettledDepartures($businessDate),
                NightAuditCheckType::UNASSIGNED_ARRIVALS        => $this->unassignedArrivals($businessDate),
                NightAuditCheckType::DIRTY_ROOMS                => $this->dirtyRooms(),
                NightAuditCheckType::OPEN_HIGH_PRIORITY_TICKETS => $this->openHighPriorityTickets(),
                NightAuditCheckType::OPEN_FOLIO_DISPUTES        => $this->openFolioDisputes(),
            };
        }

        return $result;
    }

    /** Departing on D, not pending/cancelled, with no folio or an open one (zero balance included). */
    private function unsettledDepartures(string $date): array
    {
        $query = Reservation::query()
            ->whereDate('check_out', $date)
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN, ReservationStatus::CHECKED_OUT])
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('folio')
                ->orWhereHas('folio', fn (BuilderContract $f) => $f->where('status', FolioStatus::OPEN)));

        return $this->summarise(
            $query,
            fn (Builder $q) => $q->orderBy('booking_code')->orderBy('id')->get(['uuid', 'booking_code']),
            fn (Reservation $r) => ['reservation_uuid' => $r->uuid, 'booking_code' => $r->booking_code],
        );
    }

    /** Arriving on D (confirmed/checked in) with no room line, or a line without a room. */
    private function unassignedArrivals(string $date): array
    {
        $query = Reservation::query()
            ->whereDate('check_in', $date)
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::CHECKED_IN])
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('rooms')
                ->orWhereHas('rooms', fn (BuilderContract $l) => $l->whereNull('room_id')));

        return $this->summarise(
            $query,
            fn (Builder $q) => $q->orderBy('booking_code')->orderBy('id')->get(['uuid', 'booking_code']),
            fn (Reservation $r) => ['reservation_uuid' => $r->uuid, 'booking_code' => $r->booking_code],
        );
    }

    /** Live (not trashed), active rooms whose status is dirty. */
    private function dirtyRooms(): array
    {
        $query = Room::query()
            ->where('is_active', true)
            ->where('status', RoomStatus::DIRTY);

        return $this->summarise(
            $query,
            fn (Builder $q) => $q->orderBy('number')->orderBy('id')->get(['uuid', 'number']),
            fn (Room $room) => ['room_uuid' => $room->uuid, 'number' => (string) $room->number],
        );
    }

    /** Active tickets (open/assigned/in_progress/waiting_guest) at high priority. */
    private function openHighPriorityTickets(): array
    {
        $query = Ticket::query()
            ->whereIn('status', TicketStatus::active())
            ->where('priority', '>=', self::HIGH_PRIORITY);

        return $this->summarise(
            $query,
            fn (Builder $q) => $q->orderBy('id')->get(['uuid']),
            fn (Ticket $ticket) => ['ticket_uuid' => $ticket->uuid],
        );
    }

    /** Every open dispute, regardless of reservation date or folio status (FOLIO-03: never blocks). */
    private function openFolioDisputes(): array
    {
        $query = FolioItemDispute::query()->where('folio_item_disputes.status', FolioDisputeStatus::OPEN);

        return $this->summarise(
            $query,
            fn (Builder $q) => $q
                ->join('folio_items', 'folio_items.id', '=', 'folio_item_disputes.folio_item_id')
                ->join('folios', 'folios.id', '=', 'folio_items.folio_id')
                ->orderBy('folio_item_disputes.id')
                ->get(['folio_item_disputes.uuid as dispute_uuid', 'folios.uuid as folio_uuid']),
            fn (FolioItemDispute $d) => ['dispute_uuid' => $d->dispute_uuid, 'folio_uuid' => $d->folio_uuid],
        );
    }

    /**
     * One exact COUNT plus one bounded sample.
     *
     * @param  callable(Builder): \Illuminate\Support\Collection  $sample  applies order + columns
     * @param  callable(mixed): array<string, string>  $entry  maps a row to its public evidence entry
     */
    private function summarise(Builder $query, callable $sample, callable $entry): array
    {
        $count = (clone $query)->count();

        $evidence = $sample((clone $query)->limit(self::EVIDENCE_LIMIT))
            ->map($entry)
            ->values()
            ->all();

        return [
            'issue_count'        => $count,
            'evidence'           => $evidence,
            'evidence_truncated' => $count > self::EVIDENCE_LIMIT,
        ];
    }
}
