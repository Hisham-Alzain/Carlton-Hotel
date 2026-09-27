<?php

namespace App\Exceptions;

/** Phase 5 (D-01: context reservation_uuid, reservation_status). */
class FolioMissingException extends DomainException
{
    public function errorCode(): string { return 'folio_missing'; }
    public function statusCode(): int   { return 404; }
}
