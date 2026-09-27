<?php

namespace App\Exceptions;

/** Phase 5 (D-08: context idempotency_key). */
class IdempotencyConflictException extends DomainException
{
    public function errorCode(): string { return 'idempotency_conflict'; }
    public function statusCode(): int   { return 409; }
}
