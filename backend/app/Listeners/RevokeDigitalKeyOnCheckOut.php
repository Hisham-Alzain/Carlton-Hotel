<?php

namespace App\Listeners;

use App\Actions\Booking\RevokeDigitalKeyAction;
use App\Enums\DigitalKeyRevocationReason;
use App\Events\ReservationCheckedOut;

/**
 * Ends the digital key when a stay checks out (Phase 4, D-14) — staff plain,
 * staff forced and guest express check-outs alike.
 *
 * Deliberately synchronous (not ShouldQueue): the key must be dead when the
 * check-out response returns, not when a worker gets to it. The event is
 * after-commit, so the check-out itself is already durable; should the revoke
 * fail, the expiry sweep (reason expired, at check-out time) is the backstop.
 * Auto-discovered from the type hint.
 */
class RevokeDigitalKeyOnCheckOut
{
    public function __construct(private readonly RevokeDigitalKeyAction $revokeKey) {}

    public function handle(ReservationCheckedOut $event): void
    {
        $this->revokeKey->handle($event->reservation, DigitalKeyRevocationReason::CHECKED_OUT->value);
    }
}
