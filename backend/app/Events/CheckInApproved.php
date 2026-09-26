<?php

namespace App\Events;

use App\Models\CheckInApproval;
use App\Models\Reservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by ApproveCheckInAction when an approval changes into approved on
 * a confirmed or checked-in stay (Phase 4, D-14), deferred until the approval
 * transaction commits.
 *
 * It never carries the key code: queued listeners serialise model identifiers
 * only, and the listener tells the guest the key is ready without reading it.
 */
class CheckInApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly CheckInApproval $approval,
    ) {}
}
