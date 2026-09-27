<?php

namespace App\Exceptions;

/** Phase 5 (D-09/D-10: context item_uuid, dispute_uuid). */
class FolioItemDisputeOpenException extends DomainException
{
    public function errorCode(): string { return 'folio_item_dispute_open'; }
    public function statusCode(): int   { return 422; }
}
