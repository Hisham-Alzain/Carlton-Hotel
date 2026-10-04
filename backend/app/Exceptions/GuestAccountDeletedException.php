<?php

namespace App\Exceptions;

/**
 * Phase 9.1 (D-12): a staff write that would re-attach personal data (a note,
 * preferences) to a guest whose account was deleted and anonymized.
 */
class GuestAccountDeletedException extends DomainException
{
    public function errorCode(): string { return 'guest_account_deleted'; }
    public function statusCode(): int   { return 422; }
}
