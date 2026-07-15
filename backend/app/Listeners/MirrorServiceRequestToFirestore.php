<?php

namespace App\Listeners;

use App\Events\ServiceRequestPlaced;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;
use Illuminate\Contracts\Queue\ShouldQueue;

class MirrorServiceRequestToFirestore implements ShouldQueue
{
    use MirrorsToFirestore;

    public function handle(ServiceRequestPlaced $event): void
    {
        $request = $event->request;

        $this->mirrorToFirestore(
            'ops_queue',
            OperationsQueueMirror::documentId($request),
            OperationsQueueMirror::payload($request),
        );
    }
}
