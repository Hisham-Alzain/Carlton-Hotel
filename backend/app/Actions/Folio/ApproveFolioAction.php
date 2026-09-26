<?php

namespace App\Actions\Folio;

use App\Actions\Booking\CheckOutReservationAction;
use App\Enums\CheckOutMode;
use App\Models\Folio;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * The guest's express checkout (D-08): check the stay out through the shared
 * CheckOutReservationAction in GUEST_EXPRESS mode with no actor, then stamp
 * `approved_by_guest_at` on the folio it built or refreshed.
 *
 * The reservation is locked here FIRST, before delegating to the check-out
 * action, matching the lock order every other Booking action uses
 * (reservation, then folio/room-type row). A concurrent staff check-out locks
 * the reservation first too, so the two paths can never deadlock over the
 * reverse order.
 *
 * The folio is built exactly once: CheckOutReservationAction already
 * generates/refreshes it under its own lock, so this class only stamps the
 * result rather than calling GenerateFolioAction itself (which would rebuild
 * every item a second time). GUEST_EXPRESS never returns the `folio_unsettled`
 * refusal (its effective mode is never NONE), so the folio is guaranteed to
 * exist here.
 *
 * The shared action owns the status guard, `checked_out_at`, the room-dirty
 * write, the guest marker and the event, so this class never writes the
 * reservation itself. Unguarded by the folio balance until an online gateway
 * exists (deferred), and logged as `reservation.check_out_guest_express`,
 * never as a forced check-out. Everything runs in one transaction: a refused
 * check-out rolls back the approval stamp too.
 */
class ApproveFolioAction
{
    public function __construct(
        private readonly CheckOutReservationAction $checkOut,
    ) {}

    public function handle(Reservation $reservation): array
    {
        return DB::transaction(function () use ($reservation) {
            // Lock order: reservation first, before the check-out action takes
            // the folio lock, so this path and a staff check-out never
            // interleave locks in opposite orders.
            Reservation::whereKey($reservation->getKey())->lockForUpdate()->first();

            $this->checkOut->handle($reservation, CheckOutMode::GUEST_EXPRESS, null);

            $folio = Folio::where('reservation_id', $reservation->id)->lockForUpdate()->firstOrFail();
            $folio->update(['approved_by_guest_at' => now()]);

            return ['data' => $folio->fresh()->load('items'), 'code' => 200];
        });
    }
}
