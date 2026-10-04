<?php

namespace App\Exceptions;

/** Phase 9 (D-04, D-12, D-23): the date is not the current business date and has no audit; context `requested_date`, `current_business_date`. */
class NightAuditDateMismatchException extends DomainException
{
    public function errorCode(): string { return 'night_audit_date_mismatch'; }
    public function statusCode(): int   { return 422; }
}
