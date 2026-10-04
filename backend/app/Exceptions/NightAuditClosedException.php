<?php

namespace App\Exceptions;

/** Phase 9 (D-12, D-23): the audit is closed; context `business_date`, `closed_at`. */
class NightAuditClosedException extends DomainException
{
    public function errorCode(): string { return 'night_audit_closed'; }
    public function statusCode(): int   { return 422; }
}
