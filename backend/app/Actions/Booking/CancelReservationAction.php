<?php

namespace App\Actions\Booking;

use App\Actions\Loyalty\ReverseLoyaltyForReservationAction;
use App\Enums\DigitalKeyRevocationReason;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationStateException;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    public function __construct(
        private readonly RevokeDigitalKeyAction $revokeKey,
        private readonly ReverseLoyaltyForReservationAction $reverseLoyalty,
    ) {}

    public function handle(Reservation $reservation): array
    {
        // The revocation lives inside the cancellation transaction on purpose
        // (Phase 4, D-14; RESEARCH Pitfall 6): if revoking the digital key
        // fails, the cancellation rolls back rather than committing a cancelled
        // stay whose key still works.
        // Phase 10 (M-3): status re-checked under the row lock; loyalty reversal runs in the same transaction.
        DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isCancellable()) {
                throw new ReservationStateException(__('custom.errors.reservation_state'));
            }

            $locked->update(['status' => ReservationStatus::CANCELLED]);

            $this->revokeKey->handle($locked, DigitalKeyRevocationReason::CANCELLED->value);

            $this->reverseLoyalty->handle($locked);
        });

        // Keep the caller's instance in step with the status the action just changed.
        $reservation->setAttribute('status', ReservationStatus::CANCELLED)->syncOriginalAttribute('status');

        return ['data' => null, 'code' => 204];
    }
}
