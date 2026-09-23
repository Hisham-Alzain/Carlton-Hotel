import 'dart:io';

import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/pre_arrival_document.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:file_picker/file_picker.dart';
import 'package:get/get.dart';

/// Lets a booked (tier-3a) guest pick jpg/png/pdf identity documents, tag each
/// with a type (passport/id_card/visa), then upload them for e-check-in via
/// `POST /pre-arrival/documents` as EXPLICIT indexed multipart.
class PreArrivalDocumentsController extends GetxController {
  /// The selected files awaiting upload.
  final RxList<PreArrivalDocumentUpload> docs = <PreArrivalDocumentUpload>[].obs;
  final RxBool submitting = false.obs;

  /// MIME types the backend accepts (max 10MB each, enforced server-side).
  static const List<String> allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

  /// The suggested document types (the `type` field is free-form server-side).
  static const List<String> documentTypes = ['passport', 'id_card', 'visa'];

  Future<void> pickDocuments() async {
    // v12 `pickFiles` implies multiple selection; `allowMultiple` is deprecated.
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: allowedExtensions,
    );
    if (result == null) return;
    for (final file in result.files) {
      final path = file.path;
      if (path == null) continue;
      docs.add(
        PreArrivalDocumentUpload(
          type: documentTypes.first,
          filePath: path,
          mime: _mimeFor(file.extension ?? _extensionOf(path)),
        ),
      );
    }
  }

  void setType(int index, String type) {
    if (index < 0 || index >= docs.length) return;
    docs[index] = docs[index].copyWith(type: type);
  }

  void removeDoc(int index) {
    if (index < 0 || index >= docs.length) return;
    docs.removeAt(index);
  }

  Future<void> submit() async {
    if (docs.isEmpty || submitting.value) return;
    submitting.value = true;

    final built = buildMultipart(docs);
    // Self-report failures once via the switch below — suppress the uploader's
    // own auto-dialog so a failed upload doesn't surface two error UIs.
    final res = await ApiService.find.postWithFiles<List<dynamic>>(
      path: '/pre-arrival/documents',
      fields: built.fields,
      files: built.files,
      showDialog: false,
    );
    if (isClosed) return;
    submitting.value = false;

    if (res.statusCode == 201) {
      docs.clear();
      CustomSnackbars.showSuccess(message: AppTranslations.documentsSubmitted);
      Get.back();
      return;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.noActiveReservation:
        CustomSnackbars.showError(
          message: AppTranslations.documentsNeedBooking,
        );
      case ErrorCodes.validationFailed:
        CustomSnackbars.showError(
          message: res.error?.message ?? AppTranslations.documentsInvalid,
        );
      default:
        CustomSnackbars.showError(message: AppTranslations.documentsFailed);
    }
  }

  /// Builds EXPLICIT indexed multipart parts — `documents[i][type]` scalar +
  /// `documents[i][file]` file (a group of one, per-doc MIME) — matching the
  /// backend's `documents.*.type` / `documents.*.file` rules. Pure (only wraps
  /// each path in a [File], no I/O), so it is hermetically testable.
  static ({
    Map<String, dynamic> fields,
    Map<String, ({List<File> files, String mime})> files,
  })
  buildMultipart(List<PreArrivalDocumentUpload> docs) {
    final fields = <String, dynamic>{};
    final files = <String, ({List<File> files, String mime})>{};
    for (var i = 0; i < docs.length; i++) {
      final doc = docs[i];
      fields['documents[$i][type]'] = doc.type;
      files['documents[$i][file]'] = (
        files: [File(doc.filePath)],
        mime: doc.mime,
      );
    }
    return (fields: fields, files: files);
  }

  static String _extensionOf(String path) {
    final dot = path.lastIndexOf('.');
    return dot == -1 ? '' : path.substring(dot + 1);
  }

  static String _mimeFor(String extension) {
    switch (extension.toLowerCase()) {
      case 'png':
        return 'image/png';
      case 'pdf':
        return 'application/pdf';
      case 'jpg':
      case 'jpeg':
      default:
        return 'image/jpeg';
    }
  }
}
