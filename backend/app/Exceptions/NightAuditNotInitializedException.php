<?php

namespace App\Exceptions;

/** Phase 9 (D-03, D-23): no business-date state yet and no date sent; context `requires` = "date". */
class NightAuditNotInitializedException extends DomainException
{
    public function errorCode(): string { return 'night_audit_not_initialized'; }
    public function statusCode(): int   { return 422; }
}
