<?php

namespace App\Exceptions;

/** Phase 9 (D-12, D-23): a check is pending or a blocker open; context `checks_pending`, `blockers_open`. */
class NightAuditNotReadyException extends DomainException
{
    public function errorCode(): string { return 'night_audit_not_ready'; }
    public function statusCode(): int   { return 422; }
}
