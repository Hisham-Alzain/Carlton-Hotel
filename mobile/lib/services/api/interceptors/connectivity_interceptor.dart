import 'package:carlton/l10n/app_translations.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:internet_connection_checker/internet_connection_checker.dart';

/// Labels a failed request "no internet connection" when the device really is
/// offline. Skipped on web (`kIsWeb`) where the checker isn't supported.
///
/// Consulted only **after** a request fails to connect — never as a gate before
/// sending. A pre-flight check blocked every call whenever the phone had no
/// internet, even though the API may not need any: a device reaching the
/// backend over USB (`adb reverse`) or a hotel LAN is offline to the checker
/// yet fully served. It also added an internet probe to every request.
class ConnectivityInterceptor extends Interceptor {
  final InternetConnectionChecker? checker;

  ConnectivityInterceptor({required this.checker});

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final couldNotConnect =
        err.type == DioExceptionType.connectionError ||
        err.type == DioExceptionType.connectionTimeout;
    if (!couldNotConnect || kIsWeb || await checker!.hasConnection) {
      handler.next(err);
      return;
    }
    handler.next(
      DioException(
        requestOptions: err.requestOptions,
        error: AppTranslations.noInternetConnection,
        type: DioExceptionType.connectionError,
      ),
    );
  }
}
