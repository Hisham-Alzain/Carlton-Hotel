import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/api/api_exception.dart';
import 'package:dio/dio.dart';

/// Intercepts errors from Dio and converts them into [ApiException] instances
/// attached to the `DioException.error` field, so callers receive a single
/// well-typed error regardless of whether the failure was HTTP-level
/// (envelope) or network-level (timeout, no internet, etc.).
///
/// Also fires [onUnauthorized] exactly once when a 401 / `unauthorized` is
/// detected — use it to clear tokens and route to login.
class ErrorInterceptor extends Interceptor {
  final void Function() onUnauthorized;

  ErrorInterceptor({required this.onUnauthorized});

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    var apiException = _toApiException(err);

    // Only a request that actually carried a session token can represent a
    // session going stale. Pre-auth endpoints (login, OTP verify) also
    // return `unauthorized` for ordinary user error (wrong/expired code) —
    // firing the global logout+redirect for those would rip the user off
    // the OTP screen on every wrong-code entry instead of letting it show
    // an inline error.
    final hadToken =
        (err.requestOptions.headers['Authorization'] as String?)?.isNotEmpty ==
        true;
    if (apiException.isAuthError && hadToken) {
      onUnauthorized();
      // Mark it so the display layer stays silent: onUnauthorized's teardown
      // already shows the session-expired dialog and redirects. Without this
      // flag the display layer can't tell a dead session from a wrong OTP,
      // which is why it used to show nothing for either.
      apiException = apiException.asHandledGlobally();
    }

    handler.next(
      DioException(
        requestOptions: err.requestOptions,
        response: err.response,
        type: err.type,
        error: apiException,
        stackTrace: err.stackTrace,
        message: err.message,
      ),
    );
  }

  ApiException _toApiException(DioException err) {
    // ── Cancelled ─────────────────────────────────────────────────────────
    if (err.type == DioExceptionType.cancel) {
      return ApiException.client(errorCode: ErrorCodes.cancelled, message: '');
    }

    // ── Timeouts ──────────────────────────────────────────────────────────
    if (err.type == DioExceptionType.connectionTimeout ||
        err.type == DioExceptionType.sendTimeout ||
        err.type == DioExceptionType.receiveTimeout) {
      return ApiException.client(
        errorCode: ErrorCodes.requestTimeout,
        message: AppTranslations.requestTimeout,
      );
    }

    // ── No internet (set by ConnectivityInterceptor) ──────────────────────
    if (err.type == DioExceptionType.connectionError) {
      return ApiException.client(
        errorCode: ErrorCodes.noInternetConnection,
        message: AppTranslations.checkInternetConnection,
      );
    }

    // ── HTTP-level errors with a parseable envelope ───────────────────────
    final response = err.response;
    if (response != null && response.data is Map) {
      final json = Map<String, dynamic>.from(response.data as Map);
      final requestIdHeader =
          response.headers.value('x-request-id') ??
          response.requestOptions.headers['X-Request-Id']?.toString();
      final retryAfterHeader = _parseRetryAfter(
        response.headers.value('retry-after'),
      );
      return ApiException.fromResponse(
        response.statusCode ?? 0,
        json,
        requestIdHeader: requestIdHeader,
        retryAfterHeader: retryAfterHeader,
      );
    }

    // ── HTTP error without a parseable body ───────────────────────────────
    // e.g. a proxy-generated 502 with an HTML body. Derive the message from
    // the status code so the user gets something meaningful instead of a
    // blanket "Something Went Wrong!".
    if (response != null) {
      final status = response.statusCode ?? 0;
      return ApiException(
        statusCode: status,
        message: ApiException.defaultMessage(ErrorCodes.unknown, status),
        errorCode: ErrorCodes.unknown,
      );
    }

    // ── Fallback ──────────────────────────────────────────────────────────
    return ApiException.client(
      errorCode: ErrorCodes.unknown,
      message: AppTranslations.unknownError,
    );
  }

  int? _parseRetryAfter(String? raw) {
    if (raw == null) return null;
    return int.tryParse(raw);
  }
}
