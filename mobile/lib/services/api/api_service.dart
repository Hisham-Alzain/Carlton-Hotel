import 'dart:io';
import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/services/api/upload_download/file_download.dart';
import 'package:carlton/services/api/upload_download/file_upload.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import 'package:internet_connection_checker/internet_connection_checker.dart';
import 'api_client.dart';
import '../../models/api/api_exception.dart';
import 'ui/api_dialog_handler.dart';

/// Public API surface for backend calls.
///
/// All methods return the **unwrapped `data` field** from the standard
/// envelope and throw [ApiException] on any non-2xx response or network
/// error. By default, error dialogs are shown automatically — pass
/// `showErrorDialog: false` if you want to handle errors silently (e.g. when
/// you're showing inline form errors instead).
///
/// Loading dialogs are opt-in via `showLoading: true`.
///
/// Example:
/// ```dart
/// try {
///   final user = await ApiService.find.post<Map<String, dynamic>>(
///     path: '/auth/login',
///     data: {'email': email, 'password': password},
///     showLoading: true,
///   );
///   // success path
/// } on ApiException catch (e) {
///   if (e.isValidation) applyFieldErrors(e.validationErrors);
///   // other branches: error dialog was already shown by default
/// }
/// ```
class ApiService extends GetxService {
  static const String baseUrl = '';

  /// Base URL for file/image storage. Referenced by [CustomImage] to build
  /// asset URLs; set per-environment like [baseUrl].
  static const String storageBaseUrl = '';
  static const int apiTimeOutSeconds = 30;

  static ApiService get find => Get.find<ApiService>();

  late final Dio dio;
  late final ApiDialogHandler dialogs;
  late final FileUploader _uploader;
  late final FileDownloader _downloader;
  InternetConnectionChecker? _connectivityChecker;

  // ── Hooks: override these in tests or wire to your app state ───────────

  /// Returns the current auth token, or null/empty when none.
  /// Default returns null — wire to your storage layer.
  String? Function() tokenProvider = () => null;

  /// Returns the current locale code (e.g. 'ar', 'en').
  /// Default returns 'en' — wire to your SettingsService.
  String Function() localeProvider = () => 'en';

  /// Called when a 401 / unauthorized response is received.
  /// Default is a no-op — wire to your logout / route-to-login flow.
  void Function() onUnauthorized = () {};

  @override
  void onInit() {
    super.onInit();

    if (!kIsWeb) {
      _connectivityChecker = InternetConnectionChecker.createInstance();
    }

    dialogs = ApiDialogHandler();

    dio = ApiClient.build(
      baseUrl: baseUrl,
      timeout: const Duration(seconds: apiTimeOutSeconds),
      getToken: () => tokenProvider(),
      getLocale: () => localeProvider(),
      onUnauthorized: () => onUnauthorized(),
      connectivityChecker: _connectivityChecker,
    );

    _uploader = FileUploader(dio: dio, dialogs: dialogs);
    _downloader = FileDownloader(dio: dio, dialogs: dialogs);
  }

  @override
  void onClose() {
    dio.close();
    super.onClose();
  }

  // ══════════════════════════════════════════════════════════════════════
  // HTTP methods
  // ══════════════════════════════════════════════════════════════════════

  Future<ApiResponse<T>> get<T>({
    required String path,
    Map<String, dynamic>? queryParameters,
    bool showLoading = false,
    bool showErrorDialog = true,
    CancelToken? cancelToken,
  }) {
    return _request<T>(
      () => dio.get(
        path,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      showLoading: showLoading,
      showErrorDialog: showErrorDialog,
    );
  }

  Future<ApiResponse<T>> post<T>({
    required String path,
    dynamic data,
    Map<String, dynamic>? queryParameters,
    bool showLoading = false,
    bool showErrorDialog = true,
    CancelToken? cancelToken,
  }) {
    return _request<T>(
      () => dio.post(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      showLoading: showLoading,
      showErrorDialog: showErrorDialog,
    );
  }

  Future<ApiResponse<T>> put<T>({
    required String path,
    dynamic data,
    Map<String, dynamic>? queryParameters,
    bool showLoading = false,
    bool showErrorDialog = true,
    CancelToken? cancelToken,
  }) {
    return _request<T>(
      () => dio.put(
        path,
        data: data,
        queryParameters: queryParameters,
        cancelToken: cancelToken,
      ),
      showLoading: showLoading,
      showErrorDialog: showErrorDialog,
    );
  }

  Future<ApiResponse<T>> delete<T>({
    required String path,
    bool showLoading = false,
    bool showErrorDialog = true,
    CancelToken? cancelToken,
  }) {
    return _request<T>(
      () => dio.delete(path, cancelToken: cancelToken),
      showLoading: showLoading,
      showErrorDialog: showErrorDialog,
    );
  }

  // ══════════════════════════════════════════════════════════════════════
  // Multipart uploads (delegated)
  // ══════════════════════════════════════════════════════════════════════

  Future<T> postWithFiles<T>({
    required String path,
    Map<String, dynamic>? fields,
    Map<String, ({List<File> files, String mime})>? files,
    Map<String, ({Uint8List bytes, String filename, String mime})>? byteFiles,
    CancelToken? cancelToken,
    bool showDialog = true,
  }) {
    return _uploader.postWithFiles<T>(
      path: path,
      fields: fields,
      files: files,
      byteFiles: byteFiles,
      cancelToken: cancelToken,
      showDialog: showDialog,
    );
  }

  // ══════════════════════════════════════════════════════════════════════
  // Binary downloads (delegated — these do NOT use the envelope)
  // ══════════════════════════════════════════════════════════════════════

  /// Loads file bytes into memory. Returns the raw Dio [Response]; bytes
  /// are in `response.data`. Throws [DioException] on failure (not
  /// [ApiException]) because there's no envelope to parse.
  Future<Response<dynamic>> getFile({
    required String path,
    CancelToken? cancelToken,
  }) => _downloader.getFile(path: path, cancelToken: cancelToken);

  /// Streams a file to disk at [savePath]. Throws [DioException] on
  /// failure.
  Future<File> downloadFile({
    required String path,
    required String savePath,
    CancelToken? cancelToken,
  }) => _downloader.downloadFile(
    path: path,
    savePath: savePath,
    cancelToken: cancelToken,
  );

  // ══════════════════════════════════════════════════════════════════════
  // Success dialog helper (kept for backwards-compatible call sites)
  // ══════════════════════════════════════════════════════════════════════

  Future<void> handleSuccess([String? message]) => dialogs.showSuccess(message);

  // ══════════════════════════════════════════════════════════════════════
  // Core request pipeline
  // ══════════════════════════════════════════════════════════════════════

  Future<ApiResponse<T>> _request<T>(
    Future<Response<dynamic>> Function() request, {
    required bool showLoading,
    required bool showErrorDialog,
  }) async {
    if (showLoading) dialogs.showLoading();

    try {
      final response = await request();
      return _unwrap<T>(response);
    } on DioException catch (e) {
      final apiErr = e.error;
      if (apiErr is ApiException) {
        if (showErrorDialog) dialogs.showError(apiErr);
        throw apiErr;
      }
      // Defensive fallback — ErrorInterceptor should always attach an
      // ApiException, but in case it doesn't, wrap and rethrow.
      rethrow;
    } finally {
      if (showLoading) dialogs.dismiss();
    }
  }

  ApiResponse<T> _unwrap<T>(Response<dynamic> response) {
    final body = response.data;
    final statusCode = response.statusCode ?? 200;

    if (body == null) {
      return ApiResponse.raw(statusCode: statusCode, data: null as T);
    }

    if (body is Map<String, dynamic> && body.containsKey('data')) {
      return ApiResponse.fromJson(
        body,
        statusCode: statusCode,
        dataParser: (raw) => raw as T,
      );
    }

    return ApiResponse.raw(statusCode: statusCode, data: body as T);
  }
}
