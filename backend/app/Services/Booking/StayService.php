<?php

namespace App\Services\Booking;

use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;

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
    protected int $perPage = 15;

    /** At most one: the stay the guest is currently in. */
    public function active(Guest $guest): array
    {
        $data = $this->query($guest)
            ->where('status', ReservationStatus::CHECKED_IN)
            ->orderByDesc('check_in')
            ->first();

        return ['data' => $data, 'code' => 200];
    }

    /**
     * Booked but not yet arrived. `pending_verification` is excluded — that is
     * an unverified soft-hold, not a stay the guest can rely on.
     */
    public function upcoming(Guest $guest): array
    {
        $data = $this->query($guest)
            ->whereIn('status', [ReservationStatus::PENDING, ReservationStatus::CONFIRMED])
            ->whereDate('check_out', '>=', now()->startOfDay())
            ->orderBy('check_in')
            ->get();

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

    private function query(Guest $guest): Builder
    {
        return Reservation::query()->with($this->with)->where('guest_id', $guest->id);
    }
}
