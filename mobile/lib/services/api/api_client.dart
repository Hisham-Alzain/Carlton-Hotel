import 'package:carlton/services/api/logger/custom_logger.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:internet_connection_checker/internet_connection_checker.dart';
import 'interceptors/auth_interceptor.dart';
import 'interceptors/connectivity_interceptor.dart';
import 'interceptors/error_interceptor.dart';
import 'interceptors/headers_interceptor.dart';
import 'interceptors/retry_interceptor.dart';

/// Builds a fully-configured [Dio] instance with the project's interceptor
/// stack. Order matters — see comments inline.
class ApiClient {
  static const String defaultBaseUrl = '';
  static const Duration defaultTimeout = Duration(seconds: 30);

  static Dio build({
    String baseUrl = defaultBaseUrl,
    Duration timeout = defaultTimeout,
    required String? Function() getToken,
    required String Function() getLocale,
    required void Function() onUnauthorized,
    InternetConnectionChecker? connectivityChecker,
    int maxRetries = 2,
  }) {
    final dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: timeout,
        sendTimeout: kIsWeb ? null : timeout,
        receiveTimeout: timeout,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    // Interceptor order:
    // 1. Connectivity — reject early if offline.
    // 2. Headers — stamp Accept-Language and X-Request-Id.
    // 3. Auth — inject Bearer token.
    // 4. Logger (debug only) — log the fully-decorated request.
    // 5. Retry — retry transient failures BEFORE error parsing, so retries
    //    happen on the raw DioException.
    // 6. Error — last, converts the final failure into ApiException.
    dio.interceptors.add(ConnectivityInterceptor(checker: connectivityChecker));
    dio.interceptors.add(HeadersInterceptor(getLocale: getLocale));
    dio.interceptors.add(AuthInterceptor(getToken: getToken));

    if (!kReleaseMode) {
      dio.interceptors.add(
        CustomPrettyDioLogger(
          requestHeader: true,
          requestBody: true,
          responseBody: true,
        ),
      );
    }

    dio.interceptors.add(RetryInterceptor(dio: dio, maxRetries: maxRetries));
    dio.interceptors.add(ErrorInterceptor(onUnauthorized: onUnauthorized));

    return dio;
  }
}
