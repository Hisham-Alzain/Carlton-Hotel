<?php

namespace App\Adapters;

use App\Contracts\ChannelAdapterInterface;
use App\Enums\ReservationSource;

class DirectAdapter implements ChannelAdapterInterface
{
    public function source(): ReservationSource { return ReservationSource::DIRECT; }
    public function externalChannel(): string   { return 'direct'; }
}
