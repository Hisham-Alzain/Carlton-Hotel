<?php

namespace App\Actions\Booking;

use App\Enums\DigitalKeyRevocationReason;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    public function __construct(
        private readonly RevokeDigitalKeyAction $revokeKey,
    ) {}

    public function handle(Reservation $reservation): array
    {
        if (! $reservation->status->isCancellable()) {
            throw new ReservationStateException(__('custom.errors.reservation_state'));
        }

        // The revocation lives inside the cancellation transaction on purpose
        // (Phase 4, D-14; RESEARCH Pitfall 6): if revoking the digital key
        // fails, the cancellation rolls back rather than committing a cancelled
        // stay whose key still works.
        DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => ReservationStatus::CANCELLED]);

            $this->revokeKey->handle($reservation, DigitalKeyRevocationReason::CANCELLED->value);
        });

        return ['data' => null, 'code' => 204];
    }
}
