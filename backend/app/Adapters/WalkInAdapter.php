<?php

namespace App\Adapters;

use App\Contracts\ChannelAdapterInterface;
use App\Enums\ReservationSource;

/**
 * Bookings entered by staff at the front desk rather than by the guest.
 *
 * Separate from DirectAdapter so reports can tell a self-service app booking
 * apart from one reception keyed in — both are "direct" commercially, but only
 * one of them went through OTP verification.
 */
class WalkInAdapter implements ChannelAdapterInterface
{
    public function source(): ReservationSource { return ReservationSource::WALK_IN; }
    public function externalChannel(): string   { return 'walk_in'; }
}
