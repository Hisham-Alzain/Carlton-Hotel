<?php

namespace App\Services\Guest;

use App\Actions\Guest\AddGuestNoteAction;
use App\Actions\Guest\UpdateGuestPreferencesAction;
use App\Base\BaseFilter;
use App\Base\BaseService;
use App\Enums\ReservationStatus;
use App\Filters\GuestFilter;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\User;
use App\Support\GuestEntitlement;
use App\Support\HotelClock;

/**
 * Staff-side guest area (Phase 4, D-01): the directory, the profile, notes and
 * preferences.
 */
class GuestService extends BaseService
{
    protected string $model = Guest::class;

    protected ?string $filter = GuestFilter::class;

    public function __construct(
        private readonly AddGuestNoteAction $addNote,
        private readonly UpdateGuestPreferencesAction $updatePreferences,
    ) {}

    /**
     * The staff directory (D-02): every guest, with or without reservations,
     * each row carrying its precedence-based stay status and target
     * reservation on the hotel-local date.
     *
     * Exactly five queries on this path, with or without `?stay_status=`
     * (the filter adds EXISTS subqueries, not queries):
     *   1. the paginator's count
     *   2. the guests page, with `has_reservations` as an EXISTS column
     *   3. the page's live reservations (GuestEntitlement::constrainLive)
     *   4. their reservation_rooms
     *   5. their rooms
     * Row status and target are then computed in PHP over the loaded set.
     *
     * @param  array<string, mixed>  $params
     */
    public function index(array $params = [], ?BaseFilter $filter = null, ?int $perPage = null): array
    {
        $today = HotelClock::today();

        $query = Guest::query()
            ->withExists('reservations as has_reservations')
            ->with([
                'reservations' => fn ($q) => GuestEntitlement::constrainLive($q, $today),
                'reservations.rooms.room',
            ])
            ->orderBy('last_name')
            ->orderBy('name')
            ->orderBy('id')
            // Phase 9.1 (D-12): erased accounts are blank rows; shown only on request.
            ->when(! array_key_exists('account_status', $params), fn ($q) => $q->activeAccounts());

        ($filter ?? $this->makeFilter($params))?->apply($query);

        $page = $query->paginate($this->resolvePerPage($perPage))->through(fn (Guest $guest) => [
            'guest'               => $guest,
            'stay_status'         => GuestEntitlement::stayStatus($guest->reservations, (bool) $guest->has_reservations, $today),
            'current_reservation' => GuestEntitlement::targetFrom($guest->reservations, $today),
        ]);

        return ['data' => $page, 'code' => 200];
    }

    /** Stay-history window on the profile (D-04). */
    private const HISTORY_LIMIT = 25;

    /** Notes shown on the profile (D-04). */
    private const NOTES_LIMIT = 10;

    /**
     * The "guest at the counter" profile (D-03, D-04, D-05) in one bounded
     * round trip. Frozen query graph — 10 queries on a fully furnished guest
     * (an empty relation skips its eager-load query):
     *   1. stats: one guests row with withCount / withMax aggregates
     *   2. reservations: the live set OR the 25 most recent ids (derived
     *      table), so the target is loaded even outside the history window
     *   3. reservation_rooms
     *   4. rooms
     *   5. room_types
     *   6. check_in_approvals
     *   7. users (approvers)
     *   8. guest_documents
     *   9. guest_notes (10 newest)
     *  10. users (note authors)
     * The two `users` hits hang off different parents and cannot be merged by
     * eager loading. Status, target and history are then derived in PHP.
     */
    public function profile(Guest $guest): array
    {
        $today = HotelClock::today();

        // 1
        $stats = Guest::query()
            ->whereKey($guest->getKey())
            ->withCount([
                'reservations as reservations_count',
                'reservations as stays_count'     => fn ($q) => $q->where('status', ReservationStatus::CHECKED_OUT),
                'reservations as cancelled_count' => fn ($q) => $q->where('status', ReservationStatus::CANCELLED),
                'reservations as stays_total'     => fn ($q) => $q->where('status', '!=', ReservationStatus::CANCELLED),
                'notes as notes_count',
            ])
            ->withMax(['reservations as last_check_out' => fn ($q) => $q->where('status', ReservationStatus::CHECKED_OUT)], 'check_out')
            ->firstOrFail();

        $recent = Reservation::query()
            ->select('id')
            ->where('guest_id', $guest->getKey())
            ->orderByDesc('check_in')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT);

        // 2–8. MySQL refuses LIMIT directly inside IN (subquery); the derived
        // table (fromSub) works on MySQL and SQLite alike.
        $reservations = Reservation::query()
            ->where('guest_id', $guest->getKey())
            ->where(fn ($q) => $q
                ->where(fn ($live) => GuestEntitlement::constrainLive($live, $today))
                ->orWhereIn('id', fn ($sub) => $sub->select('id')->fromSub($recent, 'recent')))
            ->with([
                'rooms.room',
                'rooms.roomType',
                'checkInApproval.approver:id,uuid,name',
                'documents',
            ])
            ->get()
            ->each(fn (Reservation $r) => $r->setRelation('guest', $guest));

        $live    = $reservations->filter(fn (Reservation $r) => GuestEntitlement::isLive($r, $today))->values();
        $target  = GuestEntitlement::targetFrom($live, $today);
        $history = $reservations
            ->sort(fn (Reservation $a, Reservation $b) => [$b->check_in->toDateString(), $b->id] <=> [$a->check_in->toDateString(), $a->id])
            ->take(self::HISTORY_LIMIT)
            ->values();

        // 9–10
        $notes = $guest->notes()
            ->with('author:id,uuid,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::NOTES_LIMIT)
            ->get();

        $reservationsCount = (int) $stats->reservations_count;

        return [
            'data' => [
                'guest'               => $guest,
                'stay_status'         => GuestEntitlement::stayStatus($live, $reservationsCount > 0, $today),
                'stats'               => [
                    'stays_count'     => (int) $stats->stays_count,
                    'cancelled_count' => (int) $stats->cancelled_count,
                    'last_check_out'  => $stats->last_check_out ? substr((string) $stats->last_check_out, 0, 10) : null,
                ],
                'current_reservation' => $target,
                'history'             => $history,
                'stays_total'         => (int) $stats->stays_total,
                'has_more'            => $reservationsCount > self::HISTORY_LIMIT,
                'notes'               => $notes,
                'notes_count'         => (int) $stats->notes_count,
            ],
            'code' => 200,
        ];
    }

    /** Newest first; the id tie-break keeps same-second notes stable. */
    public function notes(Guest $guest, ?int $perPage): array
    {
        return [
            'data' => $guest->notes()
                ->with('author:id,uuid,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($this->resolvePerPage($perPage)),
            'code' => 200,
        ];
    }

    public function addNote(Guest $guest, User $author, string $body): array
    {
        return $this->addNote->handle($guest, $author, $body);
    }

    public function updatePreferences(Guest $guest, array $data, User $actor): array
    {
        return $this->updatePreferences->handle($guest, $data, $actor);
    }
}
