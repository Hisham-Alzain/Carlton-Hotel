<?php

namespace App\Services\Reports;

use App\Enums\FolioItemSource;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TicketStatus;
use App\Models\EventInquiry;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Support\HotelClock;
use App\Support\MoneyAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The reports dashboard (Phase 9, REPORT-01, D-16..D-20). Read-only
 * aggregates over a validated hotel-local period [date_from, date_to]
 * (inclusive, ≤ 31 days). Fixed number of statements whatever the volume
 * (D-22). No fabricated metrics (no ADR, RevPAR, MTD/YTD, breakdowns).
 *
 * Stay dates are pure dates compared with `whereDate()` (SQLite stores them
 * as `Y-m-d 00:00:00`); no driver-specific date SQL (julianday/DATEDIFF).
 */
class ReportService
{
    /** Reservations that hold (or held) a stay; pending/cancelled never count. */
    private const STAY_STATUSES = [
        ReservationStatus::CONFIRMED,
        ReservationStatus::CHECKED_IN,
        ReservationStatus::CHECKED_OUT,
    ];

    /** @return array<string, mixed> */
    public function dashboard(string $from, string $to): array
    {
        $days        = $this->days($from, $to);
        $generatedAt = now()->toIso8601ZuluString();
        $movement    = $this->movement($from, $to);
        // Hotel-local period → half-open UTC window (DST-safe, D-05).
        $window      = [HotelClock::dayWindow($from)[0], HotelClock::dayWindow($to)[1]];

        return [
            'period' => [
                'date_from' => $from,
                'date_to'   => $to,
                'days'      => $days,
                'timezone'  => HotelClock::timezone(),
            ],
            'generated_at' => $generatedAt,
            'occupancy'    => $this->occupancy($from, $to, $days),
            'arrivals'     => $movement['arrivals'],
            'departures'   => $movement['departures'],
            'revenue'      => $this->revenue($window),
            'collections'  => $this->collections($window),
            'open_work'    => $this->openWork($generatedAt),
        ];
    }

    /**
     * Booked room-nights (D-17): every room line of a stay counts one room,
     * assigned or not, for each night of [check_in, check_out) inside
     * [from, to + 1). One GROUP BY over distinct (check_in, check_out) pairs,
     * folded in PHP — exact and portable. Capacity is today's live, active
     * inventory × days (maintenance included).
     *
     * @return array{occupied_room_nights: int, available_room_nights: int, occupancy_rate: string}
     */
    private function occupancy(string $from, string $to, int $days): array
    {
        $periodStart = CarbonImmutable::parse($from);
        $periodEnd   = CarbonImmutable::parse($to)->addDay();

        $available = Room::query()->where('is_active', true)->count() * $days;

        $pairs = ReservationRoom::query()
            ->join('reservations', 'reservations.id', '=', 'reservation_rooms.reservation_id')
            ->whereIn('reservations.status', self::STAY_STATUSES)
            ->whereDate('reservations.check_in', '<', $periodEnd->toDateString())
            ->whereDate('reservations.check_out', '>', $from)
            ->groupBy('reservations.check_in', 'reservations.check_out')
            ->selectRaw('reservations.check_in as stay_in, reservations.check_out as stay_out, COUNT(*) as line_count')
            ->toBase()
            ->get();

        $occupied = 0;
        foreach ($pairs as $pair) {
            $in  = CarbonImmutable::parse(CarbonImmutable::parse($pair->stay_in)->toDateString());
            $out = CarbonImmutable::parse(CarbonImmutable::parse($pair->stay_out)->toDateString());

            $start = $in->max($periodStart);
            $end   = $out->min($periodEnd);
            $nights = $start < $end ? (int) $start->diffInDays($end) : 0;

            $occupied += $nights * (int) $pair->line_count;
        }

        return [
            'occupied_room_nights'  => $occupied,
            'available_room_nights' => $available,
            'occupancy_rate'        => $available === 0 ? '0.0000' : bcdiv((string) $occupied, (string) $available, 4),
        ];
    }

    /**
     * Arrivals and departures (once per reservation) with check_in / check_out
     * in [from, to], in one conditional aggregate. Bounds are half-open
     * `[from, to + 1)` on the stored value, which is correct for both MySQL
     * DATE and SQLite's `Y-m-d 00:00:00` text without any date function.
     *
     * @return array{arrivals: int, departures: int}
     */
    private function movement(string $from, string $to): array
    {
        $end = CarbonImmutable::parse($to)->addDay()->toDateString();

        $row = Reservation::query()
            ->whereIn('status', self::STAY_STATUSES)
            ->where(fn ($q) => $q
                ->where(fn ($a) => $a->where('check_in', '>=', $from)->where('check_in', '<', $end))
                ->orWhere(fn ($d) => $d->where('check_out', '>=', $from)->where('check_out', '<', $end)))
            ->toBase()
            ->selectRaw(
                'SUM(CASE WHEN check_in >= ? AND check_in < ? THEN 1 ELSE 0 END) as arrivals, '
                . 'SUM(CASE WHEN check_out >= ? AND check_out < ? THEN 1 ELSE 0 END) as departures',
                [$from, $end, $from, $end],
            )
            ->first();

        return ['arrivals' => (int) ($row->arrivals ?? 0), 'departures' => (int) ($row->departures ?? 0)];
    }

    /**
     * Posted folio lines (D-18): exact signed sums in integer cents, grouped
     * by source in one statement; every FolioItemSource key present. Reservation
     * lines are lump-sum postings that may be re-priced while a folio is open.
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window
     */
    private function revenue(array $window): array
    {
        $rows = DB::table('folio_items')
            ->where('created_at', '>=', $window[0])
            ->where('created_at', '<', $window[1])
            ->groupBy('source_type')
            ->selectRaw(
                'source_type, SUM(' . MoneyAggregate::positiveCentsExpression('amount_usd') . ') as charges, '
                . 'SUM(' . MoneyAggregate::negativeCentsExpression('amount_usd') . ') as credits, '
                . 'SUM(' . MoneyAggregate::centsExpression('amount_usd') . ') as net'
            )
            ->get();

        $charges = '0';
        $credits = '0';
        $net     = '0';
        $bySource = array_fill_keys(FolioItemSource::values(), '0');

        foreach ($rows as $row) {
            $charges = bcadd($charges, $this->cents($row->charges), 0);
            $credits = bcadd($credits, $this->cents($row->credits), 0);
            $net     = bcadd($net, $this->cents($row->net), 0);
            if (array_key_exists((string) $row->source_type, $bySource)) {
                $bySource[$row->source_type] = bcadd($bySource[$row->source_type], $this->cents($row->net), 0);
            }
        }

        return [
            'basis'       => 'posted_folio_lines',
            'currency'    => 'USD',
            'charges_usd' => MoneyAggregate::fromCents($charges),
            'credits_usd' => MoneyAggregate::fromCents($credits),
            'net_usd'     => MoneyAggregate::fromCents($net),
            'by_source'   => array_map(fn (string $cents) => MoneyAggregate::fromCents($cents), $bySource),
        ];
    }

    /**
     * Completed payments (D-18) grouped by payable type in one statement.
     * Each payment counts once (never joined to folio items, never the
     * folio's OR-joined ledger). Payable types are FQCNs (unmapped models).
     * Event deposits are collections, never revenue. Refunds are not netted.
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window
     */
    private function collections(array $window): array
    {
        $rows = DB::table('payments')
            ->where('status', 'completed')
            ->where('created_at', '>=', $window[0])
            ->where('created_at', '<', $window[1])
            ->groupBy('payable_type')
            ->selectRaw('payable_type, SUM(' . MoneyAggregate::centsExpression('amount_usd') . ') as cents')
            ->get();

        $stayTypes  = [(new Reservation)->getMorphClass(), (new Folio)->getMorphClass()];
        $eventTypes = [(new EventInquiry)->getMorphClass()];

        $stays = $events = $other = '0';
        foreach ($rows as $row) {
            $cents = $this->cents($row->cents);
            match (true) {
                in_array($row->payable_type, $stayTypes, true)  => $stays  = bcadd($stays, $cents, 0),
                in_array($row->payable_type, $eventTypes, true) => $events = bcadd($events, $cents, 0),
                default                                         => $other  = bcadd($other, $cents, 0),
            };
        }

        return [
            'basis'              => 'completed_payments',
            'refunds_included'   => false,
            'stays_usd'          => MoneyAggregate::fromCents($stays),
            'event_deposits_usd' => MoneyAggregate::fromCents($events),
            'other_usd'          => MoneyAggregate::fromCents($other),
            'total_usd'          => MoneyAggregate::fromCents(bcadd(bcadd($stays, $events, 0), $other, 0)),
        ];
    }

    /** Current open work (D-19): two grouped status counts, every key present. */
    private function openWork(string $asOf): array
    {
        $requests = $this->statusCounts('service_requests', ServiceRequestStatus::active());
        $tickets  = $this->statusCounts('tickets', TicketStatus::active());

        return [
            'basis'            => 'current_state',
            'as_of'            => $asOf,
            'service_requests' => $requests + ['total' => array_sum($requests)],
            'tickets'          => $tickets + ['total' => array_sum($tickets)],
        ];
    }

    /**
     * @param  list<\BackedEnum>  $statuses
     * @return array<string, int>
     */
    private function statusCounts(string $table, array $statuses): array
    {
        $values = array_map(fn (\BackedEnum $s) => $s->value, $statuses);
        $counts = DB::table($table)->whereIn('status', $values)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $result = [];
        foreach ($values as $value) {
            $result[$value] = (int) ($counts[$value] ?? 0);
        }

        return $result;
    }

    /** Normalise a cents aggregate (int, null or a MySQL "8600"/"8600.00" string) to an integer string. */
    private function cents(int|string|null $value): string
    {
        return $value === null ? '0' : bcadd((string) $value, '0', 0);
    }

    private function days(string $from, string $to): int
    {
        return (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
    }
}
