import 'package:dio/dio.dart';

/// Injects `Authorization: Bearer <token>` on every request when a token is
/// available. Returns null/empty from [getToken] to skip the header (e.g.
/// public routes, pre-login).
class AuthInterceptor extends Interceptor {
  final String? Function() getToken;

  AuthInterceptor({required this.getToken});

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    final token = getToken();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }
}
