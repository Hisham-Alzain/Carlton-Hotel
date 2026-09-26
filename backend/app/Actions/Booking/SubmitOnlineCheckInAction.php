<?php

namespace App\Actions\Booking;

use App\Enums\CheckInApprovalStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\OnlineCheckInClosedException;
use App\Exceptions\ReservationStateException;
use App\Models\CheckInApproval;
use App\Models\Reservation;
use App\Support\HotelClock;
use Illuminate\Support\Facades\DB;

/**
 * A guest's online check-in (D-10): records the hotel-local arrival time for a
 * confirmed stay, up to and including the arrival day, and opens a pending
 * check-in approval when none exists.
 *
 * It never touches the digital key: issuance belongs to the staff approval
 * decision and is not gated on online check-in (D-11).
 */
class SubmitOnlineCheckInAction
{
    public function handle(Reservation $reservation, string $arrivalTime): array
    {
        return DB::transaction(function () use ($reservation, $arrivalTime) {
            // Re-read under the row lock: a submission racing a cancellation
            // serialises here and sees the committed status.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReservationStatus::CONFIRMED) {
                throw new ReservationStateException(__('custom.errors.reservation_state'), [
                    'status'  => $locked->status->value,
                    'allowed' => [ReservationStatus::CONFIRMED->value],
                ]);
            }

            $today = HotelClock::today()->toDateString();

            if ($today > $locked->check_in->toDateString()) {
                throw new OnlineCheckInClosedException(__('custom.errors.online_check_in_closed'), [
                    'check_in' => $locked->check_in->toDateString(),
                    'today'    => $today,
                ]);
            }

            // A resubmission overwrites both.
            $locked->update([
                'arrival_time'                 => $arrivalTime,
                'online_check_in_submitted_at' => now(),
            ]);

            // Create-if-missing only, so an approved or rejected decision is
            // never downgraded (D-10) — unlike a document upload, which
            // deliberately re-opens the approval.
            CheckInApproval::firstOrCreate(
                ['reservation_id' => $locked->id],
                ['status' => CheckInApprovalStatus::PENDING],
            );

            return ['data' => $locked, 'code' => 200];
        });
    }
}
