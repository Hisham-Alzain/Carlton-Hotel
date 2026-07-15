import 'dart:io';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/services/api/interceptors/retry_interceptor.dart';
import 'package:carlton/services/api/ui/api_dialog_handler.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;

/// Handles binary file downloads. These calls bypass the JSON envelope —
/// they return raw bytes / files and throw `DioException` (not
/// `ApiException`) on failure, since there's no envelope to parse.
class FileDownloader {
  final Dio dio;
  final ApiDialogHandler dialogs;

  FileDownloader({required this.dio, required this.dialogs});

  /// Loads a file fully into memory and returns the [Response] containing
  /// the bytes in `response.data`. Use for small files where you need the
  /// bytes in memory (image previews, small JSON blobs).
  Future<Response<dynamic>> getFile({
    required String path,
    CancelToken? cancelToken,
  }) async {
    final progress = 0.0.obs;
    dialogs.showProgress(
      title: AppTranslations.downloading,
      progress: progress,
      cancelToken: cancelToken,
    );

    try {
      final response = await dio.get(
        path,
        cancelToken: cancelToken,
        options: Options(
          responseType: ResponseType.bytes,
          receiveTimeout: Duration.zero,
          sendTimeout: Duration.zero,
          headers: {'Accept': '*/*'},
          extra: {
            'disableLogger': true,
            RetryInterceptor.skipRetryExtraKey: true,
          },
        ),
        onReceiveProgress: (count, total) {
          if (total > -1) progress.value = count / total;
        },
      );
      return response;
    } finally {
      dialogs.dismiss();
    }
  }

  /// Streams a file directly to disk at [savePath] and returns the [File].
  /// Use for large files where loading into memory is wasteful.
  Future<File> downloadFile({
    required String path,
    required String savePath,
    CancelToken? cancelToken,
  }) async {
    final progress = 0.0.obs;
    final file = File(savePath);
    await file.create(recursive: true);

    dialogs.showProgress(
      title: AppTranslations.downloading,
      progress: progress,
      cancelToken: cancelToken,
    );

    try {
      await dio.download(
        path,
        file.path,
        cancelToken: cancelToken,
        onReceiveProgress: (received, total) {
          if (total > -1) progress.value = received / total;
        },
        options: Options(
          responseType: ResponseType.bytes,
          receiveTimeout: Duration.zero,
          sendTimeout: Duration.zero,
          headers: {'Accept': '*/*'},
          extra: {
            'disableLogger': true,
            RetryInterceptor.skipRetryExtraKey: true,
          },
        ),
      );
      return file;
    } finally {
      dialogs.dismiss();
    }
  }
}
