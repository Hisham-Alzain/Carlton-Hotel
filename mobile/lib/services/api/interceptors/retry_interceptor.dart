import 'dart:math';
import 'package:dio/dio.dart';

/// Retries failed requests with exponential backoff for transient failures.
///
/// Retries:
/// - Connection / receive timeouts
/// - Connection errors (network-level)
/// - HTTP 503 (Service Unavailable)
/// - HTTP 429 (Too Many Requests) — honors `Retry-After` header
///
/// Never retries 4xx other than 429, never retries cancelled requests.
class RetryInterceptor extends Interceptor {
  final Dio dio;
  final int maxRetries;

  RetryInterceptor({required this.dio, this.maxRetries = 2});

  static const String _retryCountKey = '_retry_count';
  static const String _skipRetryKey = '_skip_retry';

  /// Set `options.extra['_skip_retry'] = true` on a request to opt out
  /// (e.g. file uploads where retries would re-upload the whole payload).
  static const String skipRetryExtraKey = _skipRetryKey;

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final options = err.requestOptions;

    if (options.extra[_skipRetryKey] == true) {
      return handler.next(err);
    }

    final attempt = (options.extra[_retryCountKey] as int?) ?? 0;
    if (attempt >= maxRetries || !_isRetryable(err)) {
      return handler.next(err);
    }

    final delay = _delayFor(attempt, err);
    await Future.delayed(delay);

    options.extra[_retryCountKey] = attempt + 1;

    try {
      final response = await dio.fetch(options);
      return handler.resolve(response);
    } on DioException catch (e) {
      return handler.next(e);
    }
  }

  bool _isRetryable(DioException e) {
    if (e.type == DioExceptionType.cancel) return false;
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      return true;
    }
    final status = e.response?.statusCode;
    return status == 503 || status == 429;
  }

  Duration _delayFor(int attempt, DioException err) {
    // Honor Retry-After header on 429 / 503 if present.
    final retryAfter = err.response?.headers.value('retry-after');
    if (retryAfter != null) {
      final seconds = int.tryParse(retryAfter);
      if (seconds != null && seconds > 0) {
        return Duration(seconds: seconds);
      }
    }
    return Duration(seconds: pow(2, attempt).toInt());
  }
}
