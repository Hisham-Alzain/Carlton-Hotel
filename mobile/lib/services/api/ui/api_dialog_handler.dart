import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import '../../../models/api/api_exception.dart';
import '../../../constants/error_codes.dart';

/// Centralizes UI feedback for API calls: loading dialogs, progress dialogs,
/// success dialogs, and error dialogs mapped from [ApiException].
///
/// Dialogs only — this layer never shows a snackbar, and never shows anything
/// unless the call site asked for it via `showErrorDialog` / `showLoading` /
/// `showDialog`.
///
/// Kept separate from the transport layer so the network code stays testable.
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
  /// `Get.back()` pops whatever route is topmost, and GetX models snackbars as
  /// routes too — so an open snackbar would be popped instead, leaving the
  /// loader stranded underneath. This layer no longer raises snackbars itself,
  /// but a controller's own ("Request submitted") can still be on screen when a
  /// request finishes, so close any open snackbar first to guarantee the dialog
  /// is the top route.
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

  /// Shows an [ApiException] as a dialog. **The API layer never raises a
  /// snackbar** — every failure it reports needs acknowledgement, and a
  /// two-second toast is too easy to miss for something the user has to act
  /// on. Controllers are still free to use `CustomSnackbars` for their own
  /// non-API feedback ("Code copied", "Request submitted").
  ///
  /// The only per-code branching left is which *message* to show, not which
  /// surface to show it on.
  ///
  /// This is the single error-display path for the app, and it is only reached
  /// when the caller asked for it: `ApiService._request` calls it behind
  /// `showErrorDialog` and `FileUploader` behind `showDialog`. Call sites that
  /// pass the flag as false because they need to run other logic first (revert
  /// an optimistic update, set an inline `errorMessage`, etc.) may call
  /// `ApiService.find.dialogs.showError(res.error!)` themselves afterwards.
  void showError(ApiException e) {
    // Silent by contract: a cancelled request is user-initiated (usually a
    // controller disposing), and a globally handled 401 is already being
    // reported by the session-expired teardown in
    // `ApiService._handleUnauthorized`, which signs out and routes to sign-in.
    if (e.isCancelled || e.handledGlobally) return;

    CustomDialogs.showErrorDialog(message: _messageFor(e));
  }

  /// Picks the most specific text available for [e].
  String _messageFor(ApiException e) {
    switch (e.errorCode) {
      case ErrorCodes.validationFailed:
        // No screen renders field-level errors inline yet, so surface the
        // first server-side rule rather than the generic envelope message.
        final firstField = e.validationErrors.values.isNotEmpty
            ? e.validationErrors.values.first
            : null;
        return (firstField != null && firstField.isNotEmpty)
            ? firstField.first
            : e.message;

      case ErrorCodes.tooManyRequests:
        // Append the retry window when the server sent one.
        final wait = e.retryAfter != null ? ' (${e.retryAfter}s)' : '';
        return '${e.message}$wait';

      // Everything else — including `unauthorized` on a pre-auth endpoint (a
      // wrong OTP, a bad login), which reaches here only when the request
      // carried no token, since the handledGlobally guard above returns for
      // dead sessions — shows the server's own message.
      default:
        return e.message;
    }
  }
}
