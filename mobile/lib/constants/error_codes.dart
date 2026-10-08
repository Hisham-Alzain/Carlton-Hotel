/// Stable, machine-readable error codes returned by the backend in the
/// `error_code` field of the standard error envelope.
///
/// Branch UI logic on these — never on `message` or HTTP status.
/// Always include a `default` case in switches: new codes may be added.
class ErrorCodes {
  ErrorCodes._();

  // ── Auth / authorization ──────────────────────────────────────────────
  static const String unauthorized = 'unauthorized';
  static const String forbidden = 'forbidden';

  // ── Resource state ────────────────────────────────────────────────────
  static const String notFound = 'not_found';
  static const String methodNotAllowed = 'method_not_allowed';

  // ── Validation ────────────────────────────────────────────────────────
  static const String validationFailed = 'validation_failed';

  // ── Business rules ────────────────────────────────────────────────────
  static const String paymentFailed = 'payment_failed';

  /// A write repeated under an `Idempotency-Key` with a different body — a
  /// client bug, never something the guest can fix.
  static const String idempotencyConflict = 'idempotency_conflict';

  // ── Guest auth / booking flow ─────────────────────────────────────────
  static const String otpExpired = 'otp_expired';
  static const String otpInvalid = 'otp_invalid';
  static const String otpLocked = 'otp_locked';
  static const String bookingLinkFailed = 'booking_link_failed';
  static const String occupancyExceeded = 'occupancy_exceeded';
  static const String verifiedContactImmutable = 'verified_contact_immutable';
  static const String noActiveReservation = 'no_active_reservation';
  static const String noAvailability = 'no_availability';
  static const String roomOutOfOrder = 'room_out_of_order';
  static const String invalidPromo = 'invalid_promo';
  static const String reservationState = 'reservation_state';

  /// The 5-minute soft hold on a no-account booking ran out.
  static const String holdExpired = 'hold_expired';

  /// Online check-in submitted after the arrival day.
  static const String onlineCheckInClosed = 'online_check_in_closed';

  /// `DELETE /auth/guest/me` refused: an active booking, an open bill or an
  /// upcoming service booking. `context` carries `reasons` and `booking_codes`.
  static const String guestAccountDeletionBlocked =
      'guest_account_deletion_blocked';

  /// Disputing a bill line that already has an open dispute.
  static const String folioItemDisputeOpen = 'folio_item_dispute_open';

  // ── Loyalty ───────────────────────────────────────────────────────────
  static const String loyaltyProgramInactive = 'loyalty_program_inactive';
  static const String loyaltyInsufficientPoints = 'loyalty_insufficient_points';
  static const String loyaltyBelowMinimum = 'loyalty_below_minimum';
  static const String loyaltyOverCap = 'loyalty_over_cap';
  static const String loyaltyVoucherInvalid = 'loyalty_voucher_invalid';
  static const String loyaltyRewardUnavailable = 'loyalty_reward_unavailable';
  static const String loyaltyDiscountConflict = 'loyalty_discount_conflict';

  // ── Rate limiting ─────────────────────────────────────────────────────
  static const String tooManyRequests = 'too_many_requests';

  // ── Server-side ───────────────────────────────────────────────────────
  static const String serverError = 'server_error';
  static const String serviceUnavailable = 'service_unavailable';

  // ── Client-side fallback ──────────────────────────────────────────────
  /// Used when no error_code can be parsed from the response (e.g. network
  /// failures, malformed responses).
  static const String unknown = 'unknown';
  static const String noInternetConnection = 'no_internet_connection';
  static const String requestTimeout = 'request_timeout';
  static const String cancelled = 'cancelled';
}
