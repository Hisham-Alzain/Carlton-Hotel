<?php

namespace App\Exceptions;

/** Phase 9 (D-12, D-23): the check/blocker is no longer pending/open; context `item` (check|blocker), `status`. */
class NightAuditItemResolvedException extends DomainException
{
    public function errorCode(): string { return 'night_audit_item_resolved'; }
    public function statusCode(): int   { return 422; }
}
