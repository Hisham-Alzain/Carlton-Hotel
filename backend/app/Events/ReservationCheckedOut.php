<?php

namespace App\Events;

use App\Enums\CheckOutMode;
use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by CheckOutReservationAction, deferred until the outermost
 * transaction commits (so a refused or rolled-back check-out never emits it).
 *
 * No listener ships in Phase 3: Phase 6 (turnover task) and Phase 4
 * (digital-key invalidation) subscribe later (D-09). `actorId` is the staff
 * user's id, or null for a guest express checkout.
 */
class ReservationCheckedOut implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly CheckOutMode $mode,
        public readonly ?int $actorId,
    ) {}
}
