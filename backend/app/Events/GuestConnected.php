<?php

namespace App\Events;

use App\Models\Guest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestConnected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Guest $guest) {}
}
