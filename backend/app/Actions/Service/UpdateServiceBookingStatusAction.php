<?php

namespace App\Actions\Service;

use App\Enums\ServiceBookingStatus;
use App\Exceptions\ServiceBookingTransitionException;
use App\Models\ServiceBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The staff writer of service-booking status (Phase 6, D-22), used by the
 * departure-services route for transfer bookings.
 *
 * Locks the booking row, enforces ServiceBookingStatus::allowedTargets()
 * (pending → confirmed|cancelled, confirmed → completed|cancelled, terminal
 * otherwise) and records one `service_booking.status_changed` activity entry
 * with from, to and reason beside the model's LogsActivity diff — bookings
 * have no status-history table (FA-6.08-3).
 *
 * Billing note: GenerateFolioAction bills bookings in confirmed or completed,
 * so confirming a transfer is what makes the next folio generation bill it.
 */
class UpdateServiceBookingStatusAction
{
    public function handle(ServiceBooking $booking, ServiceBookingStatus $to, ?User $actor, ?string $reason = null): array
    {
        return DB::transaction(function () use ($booking, $to, $actor, $reason) {
            $locked = ServiceBooking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $from   = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new ServiceBookingTransitionException(
                    __('custom.errors.service_booking_transition_invalid'),
                    [
                        'from'    => $from->value,
                        'to'      => $to->value,
                        'allowed' => array_map(static fn (ServiceBookingStatus $s) => $s->value, $from->allowedTargets()),
                    ],
                );
            }

            $locked->status = $to;
            $locked->save();

            $entry = activity()->performedOn($locked);
            // causedBy(null) would fall back to the authenticated user; a
            // system change must carry no causer at all.
            $actor ? $entry->causedBy($actor) : $entry->causedByAnonymous();
            $entry->withProperties(['from' => $from->value, 'to' => $to->value, 'reason' => $reason])
                ->log('service_booking.status_changed');

            return ['data' => $locked->fresh(), 'code' => 200];
        }, 3);
    }
}
