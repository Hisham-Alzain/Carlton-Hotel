part of 'stays_controller.dart';

extension StaysReceiptActions on StaysController {
  Future<void> showReceipt(Stay stay) async {
    if (!stay.hasReceipt) return;
    final res = await _api.get<Map<String, dynamic>>(
      path: '/stays/${stay.uuid}/receipt',
      showLoading: true,
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (!res.hasData) {
      CustomSnackbars.showError(message: AppTranslations.receiptLoadFailed);
      return;
    }
    final data = _receiptToData(stay, Receipt.fromJson(res.data!));
    CustomBottomSheet.show<void>(
      title: AppTranslations.receipt,
      subtitle: '${stay.roomName} · ${data.dateLabel}',
      child: ReceiptSheet(receipt: data),
      actions: CustomFilledButton(
        width: double.infinity,
        backgroundColor: AppColors.lagoonTeal,
        onPressed: () {
          Get.back();
          downloadReceiptPdf(stay);
        },
        child: Text(AppTranslations.downloadPdfReceipt),
      ),
    );
  }

  /// Streams the PDF to a temp file, then opens the system share sheet so the
  /// guest can save it to Files, print it or send it. `downloadFile` bypasses
  /// the envelope and throws a raw [DioException], so it gets its own
  /// try/catch.
  Future<void> downloadReceiptPdf(Stay stay) async {
    try {
      final dir = await getTemporaryDirectory();
      final code = stay.resCode ?? '';
      final fileTag = code.isNotEmpty ? code : stay.uuid;
      final savePath = '${dir.path}/receipt-$fileTag.pdf';
      await _api.downloadFile(
        path: '/stays/${stay.uuid}/receipt/pdf',
        savePath: savePath,
        cancelToken: _cancel,
      );
      if (isClosed) return;
      await SharePlus.instance.share(
        ShareParams(
          files: [XFile(savePath, mimeType: 'application/pdf')],
          subject: AppTranslations.receipt,
        ),
      );
    } on DioException catch (_) {
      if (isClosed) return;
      CustomSnackbars.showError(message: AppTranslations.receiptDownloadFailed);
    }
  }
}
