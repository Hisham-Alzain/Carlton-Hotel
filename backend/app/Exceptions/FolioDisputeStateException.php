<?php

namespace App\Exceptions;

/** Phase 5 (D-11: context item_uuid, status). */
class FolioDisputeStateException extends DomainException
{
    public function errorCode(): string { return 'folio_dispute_state'; }
    public function statusCode(): int   { return 422; }
}
