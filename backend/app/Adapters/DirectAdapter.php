<?php

namespace App\Adapters;

use App\Contracts\ChannelAdapterInterface;

class DirectAdapter implements ChannelAdapterInterface
{
    public function source(): string          { return 'direct'; }
    public function externalChannel(): string { return 'direct'; }
}
