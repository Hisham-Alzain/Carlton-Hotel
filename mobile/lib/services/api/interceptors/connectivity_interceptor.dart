import 'package:carlton/l10n/app_translations.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:internet_connection_checker/internet_connection_checker.dart';

/// Rejects requests with a synthetic [DioException] when the device has no
/// internet connection. Skipped on web (`kIsWeb`) where the checker isn't
/// supported.
class ConnectivityInterceptor extends Interceptor {
  final InternetConnectionChecker? checker;

  ConnectivityInterceptor({required this.checker});

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final hasConnection = kIsWeb ? true : await checker!.hasConnection;

    if (hasConnection) {
      handler.next(options);
    } else {
      handler.reject(
        DioException(
          requestOptions: options,
          error: AppTranslations.noInternetConnection,
          type: DioExceptionType.connectionError,
        ),
        true,
      );
    }
  }
}
