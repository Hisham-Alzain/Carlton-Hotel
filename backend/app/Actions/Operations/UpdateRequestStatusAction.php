<?php

namespace App\Actions\Operations;

use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;

class UpdateRequestStatusAction
{
    use MirrorsToFirestore;

    public function handle(ServiceRequest|Ticket $item, string $status): array
    {
        $item->update(['status' => $status]);
        $item->refresh();

        $this->mirrorToFirestore('ops_queue', OperationsQueueMirror::documentId($item), OperationsQueueMirror::payload($item));

        return ['data' => $item, 'code' => 200];
    }
}
