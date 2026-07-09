import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

/// Stamps every outgoing request with:
/// - `Accept: application/json`
/// - `Accept-Language` (resolved at request time)
/// - `X-Request-Id` (uuid v4, only if caller hasn't already set one)
class HeadersInterceptor extends Interceptor {
  final String Function() getLocale;
  final Uuid _uuid = const Uuid();

  HeadersInterceptor({required this.getLocale});

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers['Accept'] = 'application/json';
    options.headers['Accept-Language'] = getLocale();
    options.headers.putIfAbsent('X-Request-Id', () => _uuid.v4());
    handler.next(options);
  }
}
