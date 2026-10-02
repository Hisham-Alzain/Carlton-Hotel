<?php

namespace App\Exceptions;

/** A queue claim on an item already assigned to someone else (Phase 7, D-22, D-25). Context: {assigned_user_uuid}. */
class QueueItemAlreadyClaimedException extends DomainException
{
    public function errorCode(): string { return 'queue_item_already_claimed'; }
    public function statusCode(): int   { return 409; }
}
