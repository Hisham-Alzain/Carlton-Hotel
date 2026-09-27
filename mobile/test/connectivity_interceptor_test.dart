import 'package:carlton/services/api/interceptors/connectivity_interceptor.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:internet_connection_checker/internet_connection_checker.dart';

/// A device the internet checker considers offline.
class _OfflineChecker implements InternetConnectionChecker {
  @override
  Future<bool> get hasConnection async => false;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Stands in for the network: answers every request, or fails it to connect.
class _FakeNetwork extends Interceptor {
  _FakeNetwork({required this.reachable});

  final bool reachable;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    if (reachable) {
      handler.resolve(Response(requestOptions: options, statusCode: 200));
    } else {
      handler.reject(
        DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
        ),
        true,
      );
    }
  }
}

Dio _dio({required bool reachable}) => Dio()
  ..interceptors.addAll([
    ConnectivityInterceptor(checker: _OfflineChecker()),
    _FakeNetwork(reachable: reachable),
  ]);

void main() {
  test(
    'an offline device still reaches a reachable API (adb reverse, LAN)',
    () async {
      final response = await _dio(reachable: true).get('http://localhost/x');

      expect(response.statusCode, 200);
    },
  );

  test(
    'a failed connection on an offline device is labelled no-internet',
    () async {
      await expectLater(
        _dio(reachable: false).get('http://localhost/x'),
        throwsA(
          isA<DioException>()
              .having((e) => e.type, 'type', DioExceptionType.connectionError)
              .having((e) => e.error, 'error', isNotNull),
        ),
      );
    },
  );
}
