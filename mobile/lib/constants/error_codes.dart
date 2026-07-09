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
  static const String routeNotFound = 'route_not_found';
  static const String methodNotAllowed = 'method_not_allowed';

  // ── Validation ────────────────────────────────────────────────────────
  static const String validationFailed = 'validation_failed';

  // ── Business rules ────────────────────────────────────────────────────
  static const String businessRuleViolation = 'business_rule_violation';
  static const String outOfStock = 'out_of_stock';
  static const String insufficientBalance = 'insufficient_balance';
  static const String conflict = 'conflict';
  static const String paymentFailed = 'payment_failed';

  // ── Rate limiting ─────────────────────────────────────────────────────
  static const String tooManyRequests = 'too_many_requests';

  // ── Server-side ───────────────────────────────────────────────────────
  static const String serverError = 'server_error';
  static const String databaseError = 'database_error';
  static const String externalServiceFailed = 'external_service_failed';
  static const String serviceUnavailable = 'service_unavailable';

  // ── Client-side fallback ──────────────────────────────────────────────
  /// Used when no error_code can be parsed from the response (e.g. network
  /// failures, malformed responses).
  static const String unknown = 'unknown';
  static const String noInternetConnection = 'no_internet_connection';
  static const String requestTimeout = 'request_timeout';
  static const String cancelled = 'cancelled';
}
