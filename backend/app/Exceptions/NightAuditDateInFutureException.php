<?php

namespace App\Exceptions;

/** Phase 9 (D-04, D-23): initialization date after the hotel's today; context `requested_date`, `hotel_today`. */
class NightAuditDateInFutureException extends DomainException
{
    public function errorCode(): string { return 'night_audit_date_in_future'; }
    public function statusCode(): int   { return 422; }
}
