import '../../constants/error_codes.dart';
import '../../l10n/app_translations.dart';

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

  /// True when a global handler already owns this error's UI, so the display
  /// layer must stay silent to avoid double-reporting.
  ///
  /// Only set for 401s on requests that actually carried a token: those mean
  /// the session died, and `ApiService.onUnauthorized` +
  /// `MiddlewareService.validateSession` already clear the session, show the
  /// session-expired dialog and redirect. A 401 *without* a token is ordinary
  /// user error on a pre-auth endpoint (a wrong OTP code, a bad login) and
  /// must show its message normally.
  final bool handledGlobally;

  ApiException({
    required this.statusCode,
    required this.message,
    required this.errorCode,
    this.context = const {},
    this.validationErrors = const {},
    this.requestId,
    this.retryAfter,
    this.handledGlobally = false,
  });

  /// Returns a copy with [handledGlobally] set — used by `ErrorInterceptor`
  /// once it has decided whether to fire the global unauthorized hook.
  ApiException asHandledGlobally() => ApiException(
    statusCode: statusCode,
    message: message,
    errorCode: errorCode,
    context: context,
    validationErrors: validationErrors,
    requestId: requestId,
    retryAfter: retryAfter,
    handledGlobally: true,
  );

  /// User-facing fallback when the envelope carries no usable `message` —
  /// some error envelopes (429 among them) send only `error_code`, and a
  /// proxy-generated 502 may send no envelope at all. Resolves by
  /// `error_code` first, then by raw HTTP status, so an entirely unmapped
  /// response still produces something the user can read rather than a bare
  /// "Something Went Wrong!".
  ///
  /// Public so `ErrorInterceptor` can use it for bodiless HTTP errors.
  static String defaultMessage(String errorCode, [int statusCode = 0]) {
    final byCode = switch (errorCode) {
      ErrorCodes.tooManyRequests => AppTranslations.tooManyRequests,
      ErrorCodes.serviceUnavailable => AppTranslations.serviceUnavailable,
      ErrorCodes.serverError || ErrorCodes.databaseError =>
        AppTranslations.serverError,
      ErrorCodes.forbidden => AppTranslations.forbiddenRequest,
      ErrorCodes.notFound || ErrorCodes.routeNotFound =>
        AppTranslations.resourceNotFound,
      ErrorCodes.requestTimeout => AppTranslations.requestTimeout,
      ErrorCodes.noInternetConnection =>
        AppTranslations.checkInternetConnection,
      _ => null,
    };
    if (byCode != null) return byCode;

    return switch (statusCode) {
      403 => AppTranslations.forbiddenRequest,
      404 => AppTranslations.resourceNotFound,
      408 => AppTranslations.requestTimeout,
      429 => AppTranslations.tooManyRequests,
      503 => AppTranslations.serviceUnavailable,
      >= 500 && < 600 => AppTranslations.serverError,
      _ => AppTranslations.unknownError,
    };
  }

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

    final errorCode = json['error_code']?.toString() ?? ErrorCodes.unknown;
    final rawMessage = json['message']?.toString();

    return ApiException(
      statusCode: statusCode,
      message: (rawMessage == null || rawMessage.isEmpty)
          ? defaultMessage(errorCode, statusCode)
          : rawMessage,
      errorCode: errorCode,
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
