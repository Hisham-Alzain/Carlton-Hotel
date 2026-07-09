<?php

namespace App\Contracts;

// Seam for future OTA / channel-manager adapters (Direction A, §6).
// DirectAdapter is the only implementation at launch.
interface ChannelAdapterInterface
{
    public function source(): string;
    public function externalChannel(): string;
}
