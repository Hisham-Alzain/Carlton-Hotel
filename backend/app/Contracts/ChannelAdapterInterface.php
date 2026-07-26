<?php

namespace App\Contracts;

use App\Enums\ReservationSource;

// Seam for future OTA / channel-manager adapters (Direction A, §6).
// DirectAdapter is the only implementation at launch.
interface ChannelAdapterInterface
{
    public function source(): ReservationSource;
    public function externalChannel(): string;
}
