import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart' hide Response;
import 'package:get_storage/get_storage.dart';

/// Answers every request with `{"data": {...}}` after [gate] opens, and counts
/// what reached the network.
class _CountingAdapter implements HttpClientAdapter {
  final hits = <String>[];
  Completer<void> gate = Completer<void>();

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    hits.add('${options.method} ${options.path}');
    await gate.future;
    return ResponseBody.fromString(
      jsonEncode({
        'data': {'n': hits.length},
      }),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// Home, Stays, Services and check-in all ask for the same stay when the
/// session changes. These guard that they share one request, and that a write
/// stops a read that began before it from being shared after it.
void main() {
  late Directory tempDir;
  late ApiService api;
  late _CountingAdapter adapter;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    tempDir = await Directory.systemTemp.createTemp('carlton_shared_get');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('plugins.flutter.io/path_provider'),
          (call) async => tempDir.path,
        );
    await GetStorage.init();
  });

  tearDownAll(() async {
    try {
      if (tempDir.existsSync()) await tempDir.delete(recursive: true);
    } on FileSystemException {
      // Windows keeps the box file open; left for the OS to reap.
    }
  });

  setUp(() {
    Get.testMode = true;
    Get.put<SettingsService>(SettingsService());
    api = Get.put(ApiService());
    adapter = _CountingAdapter();
    api.dio.httpClientAdapter = adapter;
  });

  tearDown(Get.reset);

  Future<ApiResponse<Map<String, dynamic>>> getActive() => api
      .get<Map<String, dynamic>>(path: '/stays/active', showErrorDialog: false);

  test('identical GETs in flight share one request', () async {
    final calls = [getActive(), getActive(), getActive()];
    adapter.gate.complete();
    final results = await Future.wait(calls);

    expect(adapter.hits, ['GET /stays/active']);
    expect(results.every((r) => r.ok && r.data!['n'] == 1), isTrue);
  });

  test('a GET after the first finished goes to the network again', () async {
    adapter.gate.complete();
    await getActive();
    await getActive();
    expect(adapter.hits.length, 2);
  });

  test('different query parameters are not shared', () async {
    final a = api.get<Map<String, dynamic>>(
      path: '/stays/past',
      queryParameters: {'page': 1},
      showErrorDialog: false,
    );
    final b = api.get<Map<String, dynamic>>(
      path: '/stays/past',
      queryParameters: {'page': 2},
      showErrorDialog: false,
    );
    adapter.gate.complete();
    await Future.wait([a, b]);
    expect(adapter.hits.length, 2);
  });

  test('a write stops a read started before it from being shared', () async {
    final before = getActive();
    final write = api.post<Map<String, dynamic>>(
      path: '/stays/check-in',
      showErrorDialog: false,
    );
    adapter.gate.complete();
    await write;
    final after = getActive();
    await Future.wait([before, after]);

    expect(adapter.hits, [
      'GET /stays/active',
      'POST /stays/check-in',
      'GET /stays/active',
    ]);
  });

  test('one caller cancelling does not cancel the others', () async {
    final token = CancelToken();
    final cancelled = api.get<Map<String, dynamic>>(
      path: '/stays/active',
      showErrorDialog: false,
      cancelToken: token,
    );
    final kept = getActive();
    token.cancel();
    adapter.gate.complete();

    expect((await cancelled).isCancelled, isTrue);
    expect((await kept).ok, isTrue);
    expect(adapter.hits.length, 1);
  });
}
