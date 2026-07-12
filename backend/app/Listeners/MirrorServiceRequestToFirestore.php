<?php

namespace App\Listeners;

use App\Events\ServiceRequestPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;

class MirrorServiceRequestToFirestore implements ShouldQueue
{
    public function handle(ServiceRequestPlaced $event): void
    {
        // Wired in P9 — mirrors the request to the Firestore ops queue for live staff dashboards.
    }
}
