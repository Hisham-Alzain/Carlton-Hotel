import '../../constants/error_codes.dart';

/// Thrown by [ApiService] when the backend returns a non-2xx response,
/// or when a network/client-side error occurs.
///
/// Wraps the standard error envelope:
/// ```json
/// {
///   "success": false,
///   "message": "...",
///   "error_code": "...",
///   "context": { ... },
///   "errors": { ... },
///   "request_id": "..."
/// }
/// ```
class ApiException implements Exception {
  /// HTTP status code, or 0 for network/client-side errors.
  final int statusCode;

  /// Localized, user-safe message. Display this to users when no specific
  /// handling applies. Never branch on this string.
  final String message;

  /// Stable machine-readable code — branch on this. See [ErrorCodes].
  final String errorCode;

  /// Structured extra data attached to the error. Keys vary by `errorCode`.
  /// Empty map when none.
  final Map<String, dynamic> context;

  /// Field-level validation errors, keyed by form field name.
  /// Populated when `errorCode == validation_failed`. Empty otherwise.
  final Map<String, List<String>> validationErrors;

  /// Request ID echoed by the backend. Log alongside any client-side error
  /// report to allow backend log correlation.
  final String? requestId;

  /// Seconds to wait before retrying — populated from the `Retry-After`
  /// header on 429 responses, or from `context['retry_after']`.
  final int? retryAfter;

  ApiException({
    required this.statusCode,
    required this.message,
    required this.errorCode,
    this.context = const {},
    this.validationErrors = const {},
    this.requestId,
    this.retryAfter,
  });

  /// Builds an [ApiException] from a parsed JSON envelope.
  factory ApiException.fromResponse(
    int statusCode,
    Map<String, dynamic> json, {
    String? requestIdHeader,
    int? retryAfterHeader,
  }) {
    final rawErrors = json['errors'] as Map<String, dynamic>? ?? const {};
    final validationErrors = <String, List<String>>{};
    rawErrors.forEach((field, msgs) {
      if (msgs is List) {
        validationErrors[field] = msgs.map((e) => e.toString()).toList();
      } else {
        validationErrors[field] = [msgs.toString()];
      }
    });

    final context = Map<String, dynamic>.from(
      (json['context'] as Map?) ?? const {},
    );

    return ApiException(
      statusCode: statusCode,
      message: json['message']?.toString() ?? 'Unknown error',
      errorCode: json['error_code']?.toString() ?? ErrorCodes.unknown,
      context: context,
      validationErrors: validationErrors,
      requestId: json['request_id']?.toString() ?? requestIdHeader,
      retryAfter:
          retryAfterHeader ??
          (context['retry_after'] is int
              ? context['retry_after'] as int
              : null),
    );
  }

  /// Builds an [ApiException] for client-side / network errors that never
  /// produced an envelope (timeouts, no internet, etc.).
  factory ApiException.client({
    required String errorCode,
    required String message,
    int statusCode = 0,
    String? requestId,
  }) {
    return ApiException(
      statusCode: statusCode,
      message: message,
      errorCode: errorCode,
      requestId: requestId,
    );
  }

  // ── Convenience predicates ──────────────────────────────────────────────
  bool get isAuthError => errorCode == ErrorCodes.unauthorized;
  bool get isForbidden => errorCode == ErrorCodes.forbidden;
  bool get isValidation => errorCode == ErrorCodes.validationFailed;
  bool get isBusinessRule => statusCode == 409 || statusCode == 402;
  bool get isRateLimited => statusCode == 429;
  bool get isServerError => statusCode >= 500 && statusCode < 600;
  bool get isNetworkError =>
      errorCode == ErrorCodes.noInternetConnection ||
      errorCode == ErrorCodes.requestTimeout;
  bool get isCancelled => errorCode == ErrorCodes.cancelled;

  @override
  String toString() =>
      'ApiException($statusCode, $errorCode): $message'
      '${requestId != null ? ' [req=$requestId]' : ''}';
}
