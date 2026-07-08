import 'dart:io';
import 'dart:typed_data';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/api/api_exception.dart';
import 'package:carlton/services/api/interceptors/retry_interceptor.dart';
import 'package:carlton/services/api/ui/api_dialog_handler.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;

/// Handles multipart file uploads. Returns the unwrapped `data` field from
/// the standard envelope on success; throws [ApiException] on failure.
class FileUploader {
  final Dio dio;
  final ApiDialogHandler dialogs;

  FileUploader({required this.dio, required this.dialogs});

  /// Posts a multipart form with optional text fields, file paths, and
  /// in-memory byte payloads.
  ///
  /// - [fields]: scalar form fields (stringified).
  /// - [files]: file-path uploads, grouped by form field name. Each group
  ///   shares a single MIME type.
  /// - [byteFiles]: in-memory byte uploads, one per form field name.
  Future<T> postWithFiles<T>({
    required String path,
    Map<String, dynamic>? fields,
    Map<String, ({List<File> files, String mime})>? files,
    Map<String, ({Uint8List bytes, String filename, String mime})>? byteFiles,
    CancelToken? cancelToken,
    bool showDialog = true,
  }) async {
    final progress = 0.0.obs;
    final formData = FormData();

    fields?.forEach((key, value) {
      if (value != null) formData.fields.add(MapEntry(key, value.toString()));
    });

    if (files != null) {
      for (final entry in files.entries) {
        for (final file in entry.value.files) {
          formData.files.add(
            MapEntry(
              entry.key,
              await MultipartFile.fromFile(
                file.path,
                filename: file.path.split('/').last,
                contentType: DioMediaType.parse(entry.value.mime),
              ),
            ),
          );
        }
      }
    }

    byteFiles?.forEach((key, entry) {
      formData.files.add(
        MapEntry(
          key,
          MultipartFile.fromBytes(
            entry.bytes,
            filename: entry.filename,
            contentType: DioMediaType.parse(entry.mime),
          ),
        ),
      );
    });

    if (showDialog) {
      dialogs.showProgress(
        title: AppTranslations.uploading,
        progress: progress,
        cancelToken: cancelToken,
      );
    }

    try {
      final response = await dio.post(
        path,
        data: formData,
        cancelToken: cancelToken,
        options: Options(
          contentType: 'multipart/form-data',
          // Uploads should not be retried — re-uploading multipart payloads
          // is expensive and rarely the right behavior.
          extra: {RetryInterceptor.skipRetryExtraKey: true},
        ),
        onSendProgress: (sent, total) {
          if (total != -1) progress.value = sent / total;
        },
      );
      return _unwrap<T>(response);
    } on DioException catch (e) {
      final apiErr = e.error;
      if (apiErr is ApiException) {
        if (showDialog) dialogs.showError(apiErr);
        throw apiErr;
      }
      rethrow;
    } finally {
      if (showDialog) dialogs.dismiss();
    }
  }

  T _unwrap<T>(Response<dynamic> response) {
    final body = response.data;
    if (body is Map && body.containsKey('data')) {
      return body['data'] as T;
    }
    return body as T;
  }
}
