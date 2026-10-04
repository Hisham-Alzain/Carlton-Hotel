<?php

namespace App\Exceptions;

/**
 * Phase 9.1 (D-07): `DELETE /auth/guest/me` refused because the guest has a
 * live stay, an open folio or an upcoming service booking. Context:
 * `reasons` (fixed order: active_reservation, open_folio,
 * upcoming_service_booking) and `booking_codes` (sorted, at most 10).
 */
class GuestAccountDeletionBlockedException extends DomainException
{
    public function errorCode(): string { return 'guest_account_deletion_blocked'; }
    public function statusCode(): int   { return 422; }
}
