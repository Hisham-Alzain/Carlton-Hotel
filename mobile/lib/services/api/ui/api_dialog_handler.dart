import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/api/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;

/// Centralizes UI feedback for API calls: loading dialogs, progress dialogs,
/// success dialogs, and error dialogs mapped from [ApiException].
///
/// Kept separate from the transport layer so the network code stays testable
/// and so dialog behavior can be swapped wholesale (e.g. snackbars instead).
class ApiDialogHandler {
  bool _dialogOpened = false;

  // ── Loading / progress ────────────────────────────────────────────────

  void showLoading() {
    _dialogOpened = true;
    CustomDialogs.showLoadingDialog();
  }

  void showProgress({
    required String title,
    required RxDouble progress,
    CancelToken? cancelToken,
  }) {
    _dialogOpened = true;
    CustomDialogs.showProgressDialog(
      title: title,
      progress: progress,
      cancelToken: cancelToken,
    );
  }

  void dismiss() {
    if (_dialogOpened && (Get.isDialogOpen ?? false)) {
      _dialogOpened = false;
      Get.back();
    }
  }

  // ── Success ───────────────────────────────────────────────────────────

  Future<void> showSuccess([String? message]) async =>
      CustomDialogs.showSuccessDialog(text: message);

  // ── Error ─────────────────────────────────────────────────────────────

  /// Default error dialog dispatcher. Maps [ApiException] to the right
  /// CustomDialogs call based on `errorCode`. Cancelled and unauthorized
  /// errors are silent here (unauthorized is handled by the interceptor's
  /// session-expired flow).
  void showError(ApiException e) {
    if (e.isCancelled || e.isAuthError) return;

    switch (e.errorCode) {
      case ErrorCodes.noInternetConnection:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.checkInternetConnection,
        );
        break;

      case ErrorCodes.requestTimeout:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.requestTimeout,
        );
        break;

      case ErrorCodes.forbidden:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.forbiddenRequest,
          message: e.message.isNotEmpty ? e.message : null,
        );
        break;

      case ErrorCodes.notFound:
      case ErrorCodes.routeNotFound:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.resourceNotFound,
          message: e.message.isNotEmpty ? e.message : null,
        );
        break;

      case ErrorCodes.validationFailed:
        // Validation errors are typically rendered inline on form fields.
        // Fall back to a generic dialog showing the first field error so
        // callers that don't read `validationErrors` still get feedback.
        final firstField = e.validationErrors.values.isNotEmpty
            ? e.validationErrors.values.first
            : null;
        final firstMessage = firstField != null && firstField.isNotEmpty
            ? firstField.first
            : e.message;
        CustomDialogs.showErrorDialog(message: firstMessage);
        break;

      case ErrorCodes.tooManyRequests:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.tooManyRequests,
        );
        break;

      case ErrorCodes.serviceUnavailable:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.serviceUnavailable,
        );
        break;

      case ErrorCodes.serverError:
      case ErrorCodes.databaseError:
      case ErrorCodes.externalServiceFailed:
        CustomDialogs.showErrorDialog(
          errorTitle: AppTranslations.serverError,
          message: e.requestId != null
              ? '${AppTranslations.requestId}: ${e.requestId}'
              : null,
        );
        break;

      default:
        CustomDialogs.showErrorDialog(
          message: e.message.isNotEmpty
              ? e.message
              : AppTranslations.unknownError,
        );
    }
  }
}
