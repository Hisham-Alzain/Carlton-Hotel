import 'dart:io';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/constants/storage_keys.dart';
import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/services/api/upload_donwload/file_download.dart';
import 'package:carlton/services/api/upload_donwload/file_upload.dart';
import 'package:carlton/services/get_storage_service.dart';
import 'package:carlton/services/settings_service.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import 'package:internet_connection_checker/internet_connection_checker.dart';
import 'api_client.dart';
import '../../models/api/api_exception.dart';
import 'ui/api_dialog_handler.dart';

/// Public API surface for backend calls.
///
/// **These methods never throw.** Every call returns an [ApiResponse] whose
/// `data` holds the **unwrapped `data` field** from the standard envelope on
/// success, and is null on failure. Callers guard on `statusCode` and read
/// `data!` inside the guard — no try/catch anywhere:
///
/// ```dart
/// final response = await ApiService.find.post<Map<String, dynamic>>(
///   path: '/user/auth/login',
///   data: {'phone': phone},
///   showLoading: true,
/// );
/// if (response.statusCode != 200) return;
/// final user = User.fromJson(response.data!);
/// ```
///
/// By default (`showErrorDialog: true`) the failure is already on screen by
/// the time the call returns — [ApiDialogHandler.showError] maps the
/// `errorCode` to the right snackbar or dialog; see that method for the full
/// mapping. Pass `showErrorDialog: false` when the caller needs to run other
/// logic first (revert an optimistic update, set an inline `errorMessage`)
/// and report afterwards with `dialogs.showError(response.error!)`, or to
/// stay fully silent for best-effort/background calls.
///
/// [ApiResponse.error] carries the full [ApiException] when a caller needs
/// to branch — `error!.isValidation`, `error!.validationErrors`,
/// `error!.errorCode`. Cancelled requests (a disposed controller cancelling
/// its token) return a failure response and show no UI at all.
///
/// Loading dialogs are opt-in via `showLoading: true`.
class ApiService extends GetxService {
  /// Backend host root. Set at compile time via:
  ///   flutter run --dart-define=API_HOST=http://10.0.2.2:8000        (Android emulator, no adb reverse)
  ///   flutter run --dart-define=API_HOST=http://192.168.1.X:8000      (physical device over LAN)
  ///   flutter build apk --dart-define=API_HOST=https://api.offershi.com (production)
  /// Default is loopback, which works on BOTH physical devices and emulators
  /// as long as the adb tunnel is up:  adb reverse tcp:8000 tcp:8000
  /// (re-run that after replugging the USB cable).
  static const String host = String.fromEnvironment(
    'API_HOST',
    defaultValue: 'https://api.offershi.com/',
  );

  static const String baseUrl = '$host/api';
  static const String storageBaseUrl = '$host/storage/';
  static const int apiTimeOutSeconds = 30;

  static ApiService get find => Get.find<ApiService>();

  late final Dio dio;
  late final ApiDialogHandler dialogs;
  late final FileUploader _uploader;
  late final FileDownloader _downloader;
  InternetConnectionChecker? _connectivityChecker;

  // ── App-state hooks (self-wired) ───────────────────────────────────────
  //
  // These read SecureStorageService/SettingsService/MiddlewareService lazily
  // (only when a request actually runs), so it's safe for ApiService to
  // depend on them here even though it's constructed before
  // SecureStorageService.init() resolves in main.dart — by the time any of
  // these fire, startup has finished.

  /// Current auth token from secure storage, or null/empty when none.
  String? _token() => StorageService.getString(StorageKeys.token);

  /// Current locale code (e.g. 'ar', 'en') from [SettingsService].
  String _locale() => Get.find<SettingsService>().locale.value.languageCode;

  /// Fired once on a 401 / revoked token: clears the local session and
  /// bounces the user to sign-in.
  /// TODO
  void _handleUnauthorized() {}

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
      getToken: _token,
      getLocale: _locale,
      onUnauthorized: _handleUnauthorized,
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

  Future<ApiResponse<T>> postWithFiles<T>({
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

    // The loading dialog must be dismissed *before* any error UI is shown,
    // which is why this inner try/finally exists instead of one finally
    // wrapping the whole method: dialogs.dismiss() pops the top route, so
    // dismissing after showError popped the error dialog/snackbar that had
    // just been pushed — the error never appeared and the loader stayed up.
    final Response<dynamic> response;
    try {
      try {
        response = await request();
      } finally {
        if (showLoading) dialogs.dismiss();
      }
    } on DioException catch (e) {
      final apiErr = e.error is ApiException
          ? e.error as ApiException
          // Defensive fallback — ErrorInterceptor should always attach an
          // ApiException, but never let a raw DioException escape to a
          // caller that (by contract) isn't catching anything.
          : ApiException.client(
              errorCode: ErrorCodes.unknown,
              message: ApiException.defaultMessage(
                ErrorCodes.unknown,
                e.response?.statusCode ?? 0,
              ),
              statusCode: e.response?.statusCode ?? 0,
            );

      if (showErrorDialog) dialogs.showError(apiErr);
      return ApiResponse<T>.failure(apiErr);
    }

    return _unwrap<T>(response);
  }

  ApiResponse<T> _unwrap<T>(Response<dynamic> response) {
    final body = response.data;
    final statusCode = response.statusCode ?? 200;

    if (body == null) {
      return ApiResponse.raw(statusCode: statusCode, data: null);
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
