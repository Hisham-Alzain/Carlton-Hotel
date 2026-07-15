<?php

namespace App\Actions\Operations;

use App\Models\ServiceRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Support\OperationsQueueMirror;
use App\Traits\MirrorsToFirestore;

class AssignRequestAction
{
    use MirrorsToFirestore;

    public function handle(ServiceRequest|Ticket $item, User $user): array
    {
        $item->update(['assigned_user_id' => $user->id]);
        $item->refresh();

        $this->mirrorToFirestore('ops_queue', OperationsQueueMirror::documentId($item), OperationsQueueMirror::payload($item));

        return ['data' => $item, 'code' => 200];
    }
}
