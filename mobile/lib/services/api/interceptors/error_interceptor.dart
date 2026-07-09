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
    final apiException = _toApiException(err);

    if (apiException.isAuthError) {
      onUnauthorized();
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
    if (response != null) {
      return ApiException(
        statusCode: response.statusCode ?? 0,
        message: AppTranslations.unknownError,
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
