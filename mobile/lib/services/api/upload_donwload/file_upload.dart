import 'dart:io';
import 'dart:typed_data';
import 'package:carlton/l10n/app_translations.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import '../../../constants/error_codes.dart';
import '../../../models/api/api_exception.dart';
import '../../../models/api/api_response.dart';
import '../interceptors/retry_interceptor.dart';
import '../ui/api_dialog_handler.dart';

/// Handles multipart file uploads. Mirrors `ApiService`'s contract: never
/// throws, always returns an [ApiResponse] whose `data` holds the unwrapped
/// `data` field on success and is null on failure.
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
  Future<ApiResponse<T>> postWithFiles<T>({
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

    // Inner try/finally so the progress dialog is closed *before* any error
    // UI is shown — dialogs.dismiss() is a blind Get.back(), so dismissing
    // after showError would pop the error itself. Same ordering fix as
    // ApiService._request.
    final Response<dynamic> response;
    try {
      try {
        response = await dio.post(
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
      } finally {
        if (showDialog) dialogs.dismiss();
      }
    } on DioException catch (e) {
      final apiErr = e.error is ApiException
          ? e.error as ApiException
          : ApiException.client(
              errorCode: ErrorCodes.unknown,
              message: ApiException.defaultMessage(
                ErrorCodes.unknown,
                e.response?.statusCode ?? 0,
              ),
              statusCode: e.response?.statusCode ?? 0,
            );

      if (showDialog) dialogs.showError(apiErr);
      return ApiResponse<T>.failure(apiErr);
    }

    return _unwrap<T>(response);
  }

  ApiResponse<T> _unwrap<T>(Response<dynamic> response) {
    final body = response.data;
    final statusCode = response.statusCode ?? 200;

    if (body is Map && body.containsKey('data')) {
      return ApiResponse.raw(statusCode: statusCode, data: body['data'] as T?);
    }
    return ApiResponse.raw(statusCode: statusCode, data: body as T?);
  }
}
