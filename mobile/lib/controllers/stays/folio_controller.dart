import 'package:carlton/components/sheets/note_sheet_body.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';

/// Backs the My Bill screen: the guest's running folio (`GET /folio`, tier-3b).
///
/// HomeController already fetches the folio for the dashboard summary card, but
/// it keeps only the flattened `(description, amount)` pairs that card needs.
/// This holds the [Folio] itself, because the statement shows what the summary
/// drops: each line's source, payments, the balance, and open disputes. It
/// also owns "Dispute this charge" (`PATCH /folio/items/{item}/dispute`).
class FolioController extends GetxController {
  /// The server's limit on a dispute reason.
  static const int maxDisputeReasonLength = 500;

  final Rx<Folio?> folio = Rx<Folio?>(null);
  final RxBool loading = true.obs;

  /// True when the fetch failed. Distinct from "loaded with no items": a guest
  /// who has charged nothing to the room has an empty but valid folio, and only
  /// a failure should offer Retry.
  final RxBool error = false.obs;

  /// The dispute sheet's reason field. Owned here so typed text survives the
  /// keyboard rebuilding the sheet.
  final TextEditingController disputeReason = TextEditingController();
  final RxBool disputing = false.obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  @override
  void onClose() {
    disputeReason.dispose();
    super.onClose();
  }

  Future<void> load() async {
    loading.value = true;
    error.value = false;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/folio',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.hasData) {
      folio.value = Folio.fromJson(res.data!);
    } else {
      error.value = true;
    }
    loading.value = false;
  }

  /// Already approved for express checkout. The screen acts on this only by not
  /// implying the bill is still open; approval itself lives on Home.
  bool get isApproved => folio.value?.approvedByGuestAt != null;

  /// Opens the reason sheet for [item]. A line already under review cannot be
  /// disputed again until the hotel decides.
  void openDispute(FolioItem item) {
    if (item.hasOpenDispute) {
      CustomSnackbars.showInfo(message: AppTranslations.disputeAlreadyOpen);
      return;
    }
    disputeReason.clear();
    CustomBottomSheet.show<void>(
      title: AppTranslations.disputeCharge,
      subtitle: item.description,
      heightFactor: 0.6,
      child: Obx(
        () => NoteSheetBody(
          info: AppTranslations.disputeInfo,
          label: AppTranslations.disputeReasonLabel,
          controller: disputeReason,
          hintText: AppTranslations.disputeReasonHint,
          maxLines: 4,
          maxLength: maxDisputeReasonLength,
          buttonLabel: AppTranslations.sendDispute,
          buttonColor: AppColors.brickRed,
          submitting: disputing.value,
          onSubmit: () => _submitDispute(item),
        ),
      ),
    );
  }

  Future<void> _submitDispute(FolioItem item) async {
    final reason = disputeReason.text.trim();
    if (reason.isEmpty) {
      CustomSnackbars.showWarning(
        message: AppTranslations.disputeReasonRequired,
      );
      return;
    }
    if (disputing.value) return;
    disputing.value = true;
    final res = await ApiService.find.patch<Map<String, dynamic>>(
      path: '/folio/items/${item.uuid}/dispute',
      data: {'reason': reason},
      showErrorDialog: false,
    );
    if (isClosed) return;
    disputing.value = false;
    if (res.hasData) {
      final current = folio.value;
      if (current != null) {
        folio.value = current.withItem(FolioItem.fromJson(res.data!));
      }
      _closeSheet();
      CustomSnackbars.showSuccess(message: AppTranslations.disputeSent);
      return;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.folioItemDisputeOpen:
        _closeSheet();
        CustomSnackbars.showInfo(message: AppTranslations.disputeAlreadyOpen);
        load();
      // Not this guest's line any more (or gone): the bill on screen is stale.
      case ErrorCodes.notFound:
        _closeSheet();
        load();
      default:
        if (res.error != null) ApiService.find.dialogs.showError(res.error!);
    }
  }

  /// The guest may have swiped the sheet away while the request ran; a bare
  /// `Get.back()` would then pop the bill screen itself.
  void _closeSheet() {
    if (Get.isBottomSheetOpen ?? false) Get.back<void>();
  }
}
