<?php

namespace App\Exceptions;

/**
 * The service request is completed or cancelled (outside the registry's open
 * statuses), so it can no longer be assigned or claimed (Phase 7, council A1,
 * PR-1). Reused by any future service-request terminal-status guard.
 * Context: {status}.
 */
class ServiceRequestClosedException extends DomainException
{
    public function errorCode(): string { return 'service_request_closed'; }
    public function statusCode(): int   { return 422; }
}
