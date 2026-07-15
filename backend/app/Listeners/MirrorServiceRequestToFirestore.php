<?php

namespace App\Listeners;

use App\Events\ServiceRequestPlaced;
use App\Traits\MirrorsToFirestore;
use Illuminate\Contracts\Queue\ShouldQueue;

class MirrorServiceRequestToFirestore implements ShouldQueue
{
    use MirrorsToFirestore;

    public function handle(ServiceRequestPlaced $event): void
    {
        $request = $event->request;

        $this->mirrorToFirestore('ops_queue', 'service_request_' . $request->uuid, [
            'uuid'       => $request->uuid,
            'type'       => $request->type,
            'department' => $request->department,
            'status'     => $request->status,
            'priority'   => $request->priority,
            'guest_uuid' => $request->guest->uuid,
            'created_at' => $request->created_at->toIso8601String(),
        ]);
    }
}
