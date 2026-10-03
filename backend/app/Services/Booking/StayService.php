<?php

namespace App\Services\Booking;

use App\Actions\Booking\CheckInReservationAction;
use App\Actions\Booking\SubmitOnlineCheckInAction;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Guest;
use App\Models\Reservation;
use App\Support\GuestEntitlement;
use App\Support\HotelClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read projections over `reservations` for the three mobile stay screens.
 *
 * Kept separate from ReservationService because these are views, not the
 * booking lifecycle — and separate from ReservationResource because that
 * contract is already published in API_GUIDE_MOBILE.md.
 */
class StayService
{
    protected array $with = ['rooms.roomType', 'rooms.room', 'folio'];

    /**
     * Relations StayPayload reads (Phase 4, D-12) on the three screens that
     * carry it. `guest` is set from the token's guest instead of loaded.
     */
    protected array $payloadWith = ['checkInApproval', 'documents'];
    protected int $perPage = 15;

    public function __construct(
        private readonly SubmitOnlineCheckInAction $submitOnlineCheckIn,
        private readonly CheckInReservationAction $checkInReservation,
    ) {}

    /**
     * Self check-in from the app's pre-arrival wizard.
     *
     * Checks the guest into their confirmed booking through CheckInReservationAction —
     * the same transition reception performs — into the room reserved at
     * booking time. Only from the arrival day onwards, and only once the hotel
     * has confirmed the booking: a `pending` guest-made booking is refused, as
     * is an early arrival. Idempotent: a guest already in-house gets their
     * active stay back rather than an error.
     */
    public function checkIn(Guest $guest): array
    {
        $alreadyIn = $this->active($guest)['data'];
        if ($alreadyIn) {
            return ['data' => $alreadyIn, 'code' => 200];
        }

        $today       = HotelClock::today();
        $reservation = Reservation::query()
            ->where('guest_id', $guest->id)
            ->where('status', ReservationStatus::CONFIRMED)
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>', $today)
            ->orderBy('check_in')
            ->first()
            ?? throw new ReservationStateException(__('custom.errors.check_in_not_available'));

        // CheckInReservationAction is the only confirmed → checked_in writer
        // (Phase 3 D-01); AssignRoomAction never checks in (D-03). No staff
        // actor: the guest checks themselves in.
        $this->checkInReservation->handle($reservation, null, null);

        return ['data' => $this->active($guest)['data'], 'code' => 200];
    }

    /**
     * "Is the bearer of this token in the hotel right now?"
     *
     * Resolved from the token's guest via GuestEntitlement — the same source
     * of truth the `has_booking` / `is_checked_in` middleware use, so the app
     * can never disagree with the gate that will reject its next request.
     */
    public function checkInStatus(Guest $guest): array
    {
        $booked = GuestEntitlement::bookedReservations($guest);

        // One query per relation for the whole set, so the resource never lazy-loads.
        if ($booked->isNotEmpty()) {
            $booked->load(['rooms.room', ...$this->payloadWith]);
            $this->withGuest($booked, $guest);
        }

        $checkedIn = $booked->first(
            fn (Reservation $r) => $r->status === ReservationStatus::CHECKED_IN,
        );

        return [
            'data' => [
                'has_booking'   => $booked->isNotEmpty(),
                'is_checked_in' => $checkedIn !== null,
                // The in-progress stay when there is one, else the latest booking.
                'reservation'   => $checkedIn ?? $booked->sortByDesc('check_in')->first(),
            ],
            'code' => 200,
        ];
    }

    /** At most one: the stay the guest is currently in. */
    public function active(Guest $guest): array
    {
        $data = $this->query($guest)
            ->with($this->payloadWith)
            ->where('status', ReservationStatus::CHECKED_IN)
            ->orderByDesc('check_in')
            ->first();

        $data?->setRelation('guest', $guest);

        return ['data' => $data, 'code' => 200];
    }

    /**
     * Booked but not yet arrived. `pending_verification` is excluded — that is
     * an unverified soft-hold, not a stay the guest can rely on.
     */
    public function upcoming(Guest $guest): array
    {
        $data = $this->query($guest)
            ->with($this->payloadWith)
            ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::CONFIRMED])
            ->whereDate('check_out', '>=', now()->startOfDay())
            ->orderBy('check_in')
            ->get();

        $this->withGuest($data, $guest);

        return ['data' => $data, 'code' => 200];
    }

    /**
     * Online check-in (D-10), answered with the upcoming-stay payload: the
     * reservation comes back loaded exactly as upcoming() loads it.
     */
    public function onlineCheckIn(Guest $guest, Reservation $reservation, string $arrivalTime): array
    {
        $data = $this->submitOnlineCheckIn->handle($reservation, $arrivalTime)['data'];

        $data->load([...$this->with, ...$this->payloadWith]);
        $data->setRelation('guest', $guest);

        return ['data' => $data, 'code' => 200];
    }

    public function past(Guest $guest): array
    {
        $data = $this->query($guest)
            ->whereIn('status', [ReservationStatus::CHECKED_OUT, ReservationStatus::CANCELLED])
            ->orderByDesc('check_out')
            ->paginate($this->perPage);

        return ['data' => $data, 'code' => 200];
    }

    /** The token's guest owns every row here — hand it over instead of querying it. */
    private function withGuest(Collection $reservations, Guest $guest): void
    {
        $reservations->each(fn (Reservation $r) => $r->setRelation('guest', $guest));
    }

    private function query(Guest $guest): Builder
    {
        return Reservation::query()->with($this->with)->where('guest_id', $guest->id);
    }
}
