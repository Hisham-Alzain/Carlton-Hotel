import 'dart:io';
import 'package:carlton/l10n/app_translations.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import '../interceptors/retry_interceptor.dart';
import '../ui/api_dialog_handler.dart';

/// Handles binary file downloads. These calls bypass the JSON envelope —
/// they return raw bytes / files and throw `DioException` (not
/// `ApiException`) on failure, since there's no envelope to parse.
///
/// Both methods show a progress dialog by default; pass `showDialog: false`
/// for a background fetch that should stay silent. Like every other surface in
/// this layer it is dialogs only — never a snackbar.
class FileDownloader {
  final Dio dio;
  final ApiDialogHandler dialogs;

  FileDownloader({required this.dio, required this.dialogs});

  /// Raw bytes, no timeout (files can be large), no logging of the body, and
  /// no retry — re-downloading on a flaky line is the caller's call.
  static Options get _binaryOptions => Options(
    responseType: ResponseType.bytes,
    receiveTimeout: Duration.zero,
    sendTimeout: Duration.zero,
    headers: {'Accept': '*/*'},
    extra: {'disableLogger': true, RetryInterceptor.skipRetryExtraKey: true},
  );

  void _showProgress(RxDouble progress, CancelToken? cancelToken) =>
      dialogs.showProgress(
        title: AppTranslations.downloading,
        progress: progress,
        cancelToken: cancelToken,
      );

  /// Loads a file fully into memory and returns the [Response] containing
  /// the bytes in `response.data`. Use for small files where you need the
  /// bytes in memory (image previews, small JSON blobs).
  Future<Response<dynamic>> getFile({
    required String path,
    CancelToken? cancelToken,
    bool showDialog = true,
  }) async {
    final progress = 0.0.obs;
    if (showDialog) _showProgress(progress, cancelToken);

    try {
      final response = await dio.get(
        path,
        cancelToken: cancelToken,
        options: _binaryOptions,
        onReceiveProgress: (count, total) {
          if (total > -1) progress.value = count / total;
        },
      );
      return response;
    } finally {
      if (showDialog) dialogs.dismiss();
    }
  }

  /// Streams a file directly to disk at [savePath] and returns the [File].
  /// Use for large files where loading into memory is wasteful.
  Future<File> downloadFile({
    required String path,
    required String savePath,
    CancelToken? cancelToken,
    bool showDialog = true,
  }) async {
    final progress = 0.0.obs;
    final file = File(savePath);
    await file.create(recursive: true);

    if (showDialog) _showProgress(progress, cancelToken);

    try {
      await dio.download(
        path,
        file.path,
        cancelToken: cancelToken,
        onReceiveProgress: (received, total) {
          if (total > -1) progress.value = received / total;
        },
        options: _binaryOptions,
      );
      return file;
    } finally {
      if (showDialog) dialogs.dismiss();
    }
  }
}
