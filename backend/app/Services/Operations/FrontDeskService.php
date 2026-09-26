<?php

namespace App\Services\Operations;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\PricingRule;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Booking\PricingService;
use App\Support\HotelClock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Front-desk operational reads (Phase 2, ROOMS-01/03/04). Each read is
 * hand-assembled from a fixed number of set-based queries, whatever the room,
 * room-type or night count:
 *
 * - board()            — 4 queries (D-13): rooms, their room types, their
 *                        status changers, and the reservation rows touching the
 *                        business date. Occupancy is derived here from
 *                        reservations (D-02) and stored nowhere; rooms.status is
 *                        reported as housekeeping status only.
 * - availabilityGrid() — 4 queries (D-09 allows 5): room types with active room
 *                        totals, overlapping reservation rows (+ their eager
 *                        reservation), and maintenance counts. `booked` uses
 *                        CheckAvailabilityAction's exact overlap predicate, so
 *                        `free` always equals GET /public/availability.
 *                        `out_of_order` is informational and never subtracted.
 * - ratesGrid()        — 2 read queries: room types and their pricing rules;
 *                        every cell goes through PricingService::nightlyRate.
 *
 * Receives validated filters as arrays; never reads the HTTP request and
 * throws no HTTP exception. No numeric id leaves this service.
 */
class FrontDeskService
{
    private const DEFAULT_DAYS = 14;

    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @param  array{date?: ?string, status?: ?string, floor?: int|string|null, room_type?: ?string}  $filters
     */
    public function board(array $filters): array
    {
        // Default is the hotel-local date, the same "today" check-in uses (D-02).
        $date = $filters['date'] ?? HotelClock::today()->toDateString();

        // Q1 rooms, Q2 roomType, Q3 statusChangedBy.
        $rooms = Room::query()
            ->select(['id', 'uuid', 'room_type_id', 'number', 'floor', 'status', 'status_changed_at', 'status_changed_by'])
            ->where('is_active', true)
            ->when(($filters['status'] ?? null) !== null, fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['floor'] ?? null) !== null, fn ($q) => $q->where('floor', (int) $filters['floor']))
            ->when(($filters['room_type'] ?? null) !== null, fn ($q) => $q->whereHas(
                'roomType',
                fn ($t) => $t->where('uuid', $filters['room_type']),
            ))
            ->with(['roomType:id,uuid,name', 'statusChangedBy:id,uuid,name'])
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        if ($rooms->isEmpty()) {
            return ['data' => ['date' => $date, 'items' => []], 'code' => 200];
        }

        // Q4 every live reservation row of these rooms whose stay touches $date.
        $rowsByRoom = ReservationRoom::query()
            ->join('reservations', 'reservations.id', '=', 'reservation_rooms.reservation_id')
            ->leftJoin('guests', 'guests.id', '=', 'reservations.guest_id')
            ->whereIn('reservation_rooms.room_id', $rooms->pluck('id')->all())
            ->whereNotIn('reservations.status', [ReservationStatus::CANCELLED->value, ReservationStatus::CHECKED_OUT->value])
            ->whereDate('reservations.check_in', '<=', $date)
            ->whereDate('reservations.check_out', '>=', $date)
            ->toBase()
            ->get([
                'reservation_rooms.room_id as room_id',
                'reservations.uuid as reservation_uuid',
                'reservations.check_in as check_in',
                'reservations.check_out as check_out',
                'reservations.status as reservation_status',
                'reservations.last_name as stub_last_name',
                'guests.first_name as guest_first_name',
                'guests.last_name as guest_last_name',
                'guests.name as guest_name',
            ])
            ->map(fn (object $row) => [
                'room_id'   => (int) $row->room_id,
                'uuid'      => $row->reservation_uuid,
                'check_in'  => Carbon::parse($row->check_in)->toDateString(),
                'check_out' => Carbon::parse($row->check_out)->toDateString(),
                'status'    => $row->reservation_status,
                'guest'     => $this->guestName($row),
            ])
            ->groupBy('room_id');

        $items = $rooms->map(fn (Room $room) => $this->boardRow(
            $room,
            $rowsByRoom->get($room->id, collect()),
            $date,
        ))->values()->all();

        return ['data' => ['date' => $date, 'items' => $items], 'code' => 200];
    }

    /**
     * @param  array{from?: ?string, days?: int|string|null}  $params
     */
    public function availabilityGrid(array $params): array
    {
        [$from, $days, $dates, $end] = $this->window($params);

        // Q1 active room types with their active, non-deleted room count.
        $types = RoomType::query()
            ->select(['id', 'uuid', 'name'])
            ->where('is_active', true)
            ->withCount(['rooms as total' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($types->isEmpty()) {
            return ['data' => ['from' => $from, 'days' => $days, 'room_types' => []], 'code' => 200];
        }

        $typeIds = $types->pluck('id')->all();

        // Q2 + Q3 (eager reservation): CheckAvailabilityAction::overlapping()'s
        // predicate over the whole window. Rows with a null room_id still count.
        $rows = ReservationRoom::query()
            ->whereIn('room_type_id', $typeIds)
            ->whereHas('reservation', function ($q) use ($from, $end) {
                $q->whereDate('check_in', '<', $end)
                  ->whereDate('check_out', '>', $from)
                  ->holdingInventory();
            })
            ->with('reservation:id,check_in,check_out')
            ->get(['id', 'room_type_id', 'reservation_id']);

        // Q4 rooms in maintenance per type — the only read of housekeeping
        // status here, and it feeds out_of_order only.
        $outOfOrder = Room::query()
            ->whereIn('room_type_id', $typeIds)
            ->where('is_active', true)
            ->where('status', RoomStatus::MAINTENANCE->value)
            ->groupBy('room_type_id')
            ->selectRaw('room_type_id, count(*) as aggregate')
            ->toBase()
            ->pluck('aggregate', 'room_type_id');

        $booked = [];
        foreach ($rows as $row) {
            $checkIn  = Carbon::parse($row->reservation->check_in)->toDateString();
            $checkOut = Carbon::parse($row->reservation->check_out)->toDateString();
            $night    = CarbonImmutable::parse(max($checkIn, $from));
            $last     = min($checkOut, $end);

            for (; $night->toDateString() < $last; $night = $night->addDay()) {
                $key = $night->toDateString();
                $booked[$row->room_type_id][$key] = ($booked[$row->room_type_id][$key] ?? 0) + 1;
            }
        }

        $roomTypes = $types->map(function (RoomType $type) use ($dates, $booked, $outOfOrder) {
            $total = (int) $type->total;
            $ooo   = (int) ($outOfOrder[$type->id] ?? 0);

            return [
                'uuid'  => $type->uuid,
                'name'  => $type->getTranslations('name'),
                'total' => $total,
                'cells' => array_map(function (string $date) use ($type, $total, $booked, $ooo) {
                    $count = $booked[$type->id][$date] ?? 0;

                    return [
                        'date'         => $date,
                        'free'         => max(0, $total - $count),
                        'booked'       => $count,
                        'out_of_order' => $ooo,
                    ];
                }, $dates),
            ];
        })->values()->all();

        return ['data' => ['from' => $from, 'days' => $days, 'room_types' => $roomTypes], 'code' => 200];
    }

    /**
     * @param  array{from?: ?string, days?: int|string|null}  $params
     */
    public function ratesGrid(array $params): array
    {
        [$from, $days, $dates, $end] = $this->window($params);

        // Q1 active room types, same order as the availability grid.
        $types = RoomType::query()
            ->select(['id', 'uuid', 'name', 'base_price_usd'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($types->isEmpty()) {
            return ['data' => ['from' => $from, 'days' => $days, 'room_types' => []], 'code' => 200];
        }

        // Q2 every active rule touching the window, with no ordering clause:
        // the quote's natural order (D-10). groupBy() keeps that order per type.
        $rulesByType = PricingRule::query()
            ->whereIn('room_type_id', $types->pluck('id')->all())
            ->where('is_active', true)
            ->whereDate('starts_on', '<', $end)
            ->whereDate('ends_on', '>=', $from)
            ->get()
            ->groupBy('room_type_id');

        $roomTypes = $types->map(function (RoomType $type) use ($dates, $rulesByType) {
            $rules = $rulesByType->get($type->id, collect());

            return [
                'uuid'           => $type->uuid,
                'name'           => $type->getTranslations('name'),
                'base_price_usd' => $type->base_price_usd,
                'cells'          => array_map(function (string $date) use ($type, $rules) {
                    $rate = $this->pricing->nightlyRate($type, CarbonImmutable::parse($date), $rules);

                    return [
                        'date'       => $date,
                        'rate_usd'   => number_format($rate['rate_usd'], 2, '.', ''),
                        'rule_scope' => $rate['rule_scope'],
                    ];
                }, $dates),
            ];
        })->values()->all();

        return ['data' => ['from' => $from, 'days' => $days, 'room_types' => $roomTypes], 'code' => 200];
    }

    /**
     * One board row with the twelve D-12 keys. Rows reaching here are neither
     * cancelled nor checked out and satisfy check_in <= $date <= check_out.
     *
     * @param  Collection<int, array{uuid: string, check_in: string, check_out: string, status: string, guest: ?string}>  $rows
     */
    private function boardRow(Room $room, Collection $rows, string $date): array
    {
        // Deterministic pick when several rows qualify: earliest check-in, then smallest uuid.
        $rows = $rows->sortBy([['check_in', 'asc'], ['uuid', 'asc']])->values();

        $checkedIn = $rows->where('status', ReservationStatus::CHECKED_IN->value);
        $covering  = $checkedIn->first(fn (array $r) => $r['check_in'] <= $date && $date < $r['check_out']);
        $ending    = $checkedIn->first(fn (array $r) => $r['check_out'] === $date);
        $arriving  = $rows->first(fn (array $r) => $r['check_in'] === $date);
        $shown     = $covering ?? $ending ?? $arriving;

        return [
            'uuid'                => $room->uuid,
            'number'              => $room->number,
            'floor'               => $room->floor,
            'room_type'           => $room->roomType ? [
                'uuid' => $room->roomType->uuid,
                'name' => $room->roomType->getTranslations('name'),
            ] : null,
            'housekeeping_status' => $room->status->value,
            'status_changed_at'   => $room->status_changed_at?->toIso8601String(),
            'status_changed_by'   => $room->statusChangedBy ? [
                'uuid' => $room->statusChangedBy->uuid,
                'name' => $room->statusChangedBy->name,
            ] : null,
            'occupancy'           => $covering ? 'occupied' : 'vacant',
            'arriving_today'      => $arriving !== null,
            'departing_today'     => $ending !== null,
            'stayover'            => $checkedIn->contains(fn (array $r) => $r['check_in'] < $date && $date < $r['check_out']),
            'reservation'         => $shown ? [
                'uuid'       => $shown['uuid'],
                'guest_name' => $shown['guest'],
                'check_in'   => $shown['check_in'],
                'check_out'  => $shown['check_out'],
                'status'     => $shown['status'],
            ] : null,
        ];
    }

    /** First + last name, else the guest's name, else the reservation's stub last name. */
    private function guestName(object $row): ?string
    {
        $full = trim(($row->guest_first_name ?? '').' '.($row->guest_last_name ?? ''));

        return ($full !== '' ? $full : null) ?? ($row->guest_name ?: null) ?? ($row->stub_last_name ?: null);
    }

    /**
     * Grid window: `from` (default the hotel-local date, see HotelClock) for
     * `days` nights (default 14).
     *
     * @return array{0: string, 1: int, 2: list<string>, 3: string} [from, days, dates, exclusive end]
     */
    private function window(array $params): array
    {
        $from  = $params['from'] ?? HotelClock::today()->toDateString();
        $days  = (int) ($params['days'] ?? self::DEFAULT_DAYS);
        $start = CarbonImmutable::parse($from);

        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $start->addDays($i)->toDateString();
        }

        return [$from, $days, $dates, $start->addDays($days)->toDateString()];
    }
}
