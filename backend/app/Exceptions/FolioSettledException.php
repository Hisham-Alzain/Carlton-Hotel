<?php

namespace App\Exceptions;

/** Phase 5 (D-05/D-13/D-14: context folio_uuid, settled_at). */
class FolioSettledException extends DomainException
{
    public function errorCode(): string { return 'folio_settled'; }
    public function statusCode(): int   { return 422; }
}
