import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import '../../../models/api/api_exception.dart';
import '../../../constants/error_codes.dart';

/// Centralizes UI feedback for API calls: loading dialogs, progress dialogs,
/// success dialogs, and error dialogs mapped from [ApiException].
///
/// Kept separate from the transport layer so the network code stays testable
/// and so dialog behavior can be swapped wholesale (e.g. snackbars instead).
class ApiDialogHandler {
  /// Number of loading/progress dialogs this handler currently believes are
  /// open. A counter, not a bool: two overlapping `showLoading: true`
  /// requests would otherwise have the first one to finish clear the flag,
  /// stranding the second's dialog on screen forever.
  int _openDialogs = 0;

  // ── Loading / progress ────────────────────────────────────────────────

  void showLoading() {
    _openDialogs++;
    CustomDialogs.showLoadingDialog();
  }

  void showProgress({
    required String title,
    required RxDouble progress,
    CancelToken? cancelToken,
  }) {
    _openDialogs++;
    CustomDialogs.showProgressDialog(
      title: title,
      progress: progress,
      cancelToken: cancelToken,
    );
  }

  /// Closes one loading/progress dialog.
  ///
  /// `Get.back()` pops whatever route is topmost, and GetX models snackbars
  /// as routes too — so if an earlier error's snackbar is still on screen it
  /// would be popped instead, leaving the loader stranded underneath. Close
  /// any open snackbar first so the dialog is genuinely the top route.
  void dismiss() {
    if (_openDialogs <= 0) return;
    if (!(Get.isDialogOpen ?? false)) {
      _openDialogs = 0;
      return;
    }
    if (Get.isSnackbarOpen) Get.closeAllSnackbars();
    _openDialogs--;
    Get.back();
  }

  // ── Success ───────────────────────────────────────────────────────────

  Future<void> showSuccess([String? message]) async =>
      CustomDialogs.showSuccessDialog(message: message);

  // ── Error ─────────────────────────────────────────────────────────────

  /// Maps [ApiException] to the right user-facing feedback — a snackbar for
  /// transient, self-explanatory failures (validation, rate limit, offline,
  /// timeout) and a dialog for anything needing acknowledgement (server
  /// errors, unmapped codes) or when [forceDialog] is set (e.g. checkout
  /// failures).
  ///
  /// This is the single error-display path for the app. `ApiService`'s
  /// `_request` calls it automatically when `showErrorDialog: true` (the
  /// default); call sites that pass `showErrorDialog: false` because they
  /// need to run other logic first (revert an optimistic update, set an
  /// inline `errorMessage`, etc.) call
  /// `ApiService.find.dialogs.showError(res.error!)` themselves instead.
  void showError(ApiException e, {bool forceDialog = false}) {
    // Silent by contract, regardless of forceDialog: a cancelled request is
    // user-initiated (usually a controller disposing), and a globally handled
    // 401 is already being reported by the session-expired teardown.
    if (e.isCancelled || e.handledGlobally) return;

    if (forceDialog) {
      CustomDialogs.showErrorDialog(message: e.message);
      return;
    }

    switch (e.errorCode) {
      case ErrorCodes.validationFailed:
        // No screen renders field-level errors inline yet, so surface the
        // first server-side rule as a snackbar rather than staying silent.
        final firstField = e.validationErrors.values.isNotEmpty
            ? e.validationErrors.values.first
            : null;
        CustomSnackbars.showWarning(
          message: (firstField != null && firstField.isNotEmpty)
              ? firstField.first
              : e.message,
        );

      case ErrorCodes.unauthorized:
        // Reaching here means the request carried NO token (the
        // handledGlobally guard above already returned for dead sessions),
        // so this is ordinary user error on a pre-auth endpoint — a wrong or
        // expired OTP code, a bad login. Show the server's message ("Invalid
        // OTP"). This case used to `break` unconditionally on the assumption
        // that onUnauthorized covered it, which is why a wrong OTP produced
        // no feedback at all.
        CustomDialogs.showErrorDialog(message: e.message);

      case ErrorCodes.outOfStock:
        CustomSnackbars.showWarning(message: e.message);

      case ErrorCodes.tooManyRequests:
        final wait = e.retryAfter != null ? ' (${e.retryAfter}s)' : '';
        CustomSnackbars.showWarning(message: '${e.message}$wait');

      case ErrorCodes.noInternetConnection:
        CustomSnackbars.showError(message: e.message);

      case ErrorCodes.requestTimeout:
        CustomSnackbars.showError(message: e.message);

      case ErrorCodes.serverError:
      case ErrorCodes.serviceUnavailable:
        // Server errors shown as dialogs — they usually need acknowledgement.
        CustomDialogs.showErrorDialog(message: e.message);

      default:
        // Unmapped or unknown error codes get a dialog rather than a
        // two-second snackbar: these are exactly the errors the user has no
        // context for, so they need to be read and acknowledged rather than
        // flashed. The cases above keep snackbars because they're transient
        // and self-explanatory.
        CustomDialogs.showErrorDialog(message: e.message);
    }
  }
}
