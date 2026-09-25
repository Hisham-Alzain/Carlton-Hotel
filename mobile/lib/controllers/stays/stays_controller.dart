import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/components/sheets/cancel_reservation_sheet.dart';
import 'package:carlton/components/sheets/receipt_sheet.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/folio.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/receipt.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';

/// Owns the My Stays tabs (Active / Upcoming / Past) and the receipt + cancel
/// flows, wired to the real Stays/Folio API. Active is a single nullable fetch,
/// Upcoming a plain array, Past uses [PaginatedControllerMixin]. Each stream
/// loads independently — one failing doesn't blank the other two. Registered in
/// `MainBinding` since Stays is a tab.
class StaysController extends GetxController
    with GetSingleTickerProviderStateMixin, PaginatedControllerMixin<Stay> {
  late final TabController tabController;
  final CancelToken _cancel = CancelToken();
  final ApiService _api = ApiService.find;

  // ── Active (single nullable object) ────────────────────────────────────────
  final Rx<Stay?> active = Rx<Stay?>(null);
  final RxBool activeLoading = true.obs;
  final RxBool activeError = false.obs;

  // ── Upcoming (plain array, not paginated) ──────────────────────────────────
  final RxList<Stay> upcoming = <Stay>[].obs;
  final RxBool upcomingLoading = true.obs;
  final RxBool upcomingError = false.obs;

  // ── Past uses the mixin's Rx items / loading / hasError + scrollController ──

  // Reused label formatters (money via [usd]).
  static final DateFormat _fullDate = DateFormat('MMM d, yyyy');
  static final DateFormat _shortDate = DateFormat('MMM d');
  static final DateFormat _time = DateFormat('h:mm a');

  @override
  void onInit() {
    super.onInit();
    // Open on Upcoming — the tab with the actionable reservation.
    tabController = TabController(length: 3, vsync: this, initialIndex: 1);
    initPagination(_cancel);
    _loadActive();
    _loadUpcoming();
    loadItems(_cancel); // past
  }

  @override
  void onClose() {
    _cancel.cancel();
    tabController.dispose();
    // Chains into PaginatedControllerMixin.onClose → disposes scrollController.
    super.onClose();
  }

  // ══════════════════════════════════════════════════════════════════════════
  // Fetches
  // ══════════════════════════════════════════════════════════════════════════

  Future<void> _loadActive() async {
    activeLoading.value = true;
    activeError.value = false;
    // `data` is a single object OR null (not checked in). Nullable T so the
    // envelope's `null` doesn't blow up the `raw as T` cast.
    final res = await _api.get<Map<String, dynamic>?>(
      path: '/stays/active',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.ok) {
      final data = res.data;
      active.value = data == null
          ? null
          : _activeToStay(ActiveStay.fromJson(data));
    } else {
      activeError.value = true;
    }
    activeLoading.value = false;
  }

  Future<void> _loadUpcoming() async {
    upcomingLoading.value = true;
    upcomingError.value = false;
    final res = await _api.get<List<dynamic>>(
      path: '/stays/upcoming',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.statusCode == 200 && res.data != null) {
      upcoming.assignAll(
        UpcomingStay.listFromJson(res.data).map(_upcomingToStay),
      );
    } else {
      upcomingError.value = true;
    }
    upcomingLoading.value = false;
  }

  @override
  Future<({List<Stay> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await _api.get<List<dynamic>>(
      path: '/stays/past',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (res.statusCode != 200 || res.data == null) return null;
    return (
      items: PastStay.listFromJson(res.data).map(_pastToStay).toList(),
      pagination: res.meta ?? Pagination(),
    );
  }

  // View-facing retries for the loading/error states.
  void reloadActive() => _loadActive();
  void reloadUpcoming() => _loadUpcoming();
  void reloadPast() => loadItems(_cancel);

  // ══════════════════════════════════════════════════════════════════════════
  // DTO → view-model mapping (controller boundary; cards stay unchanged)
  // ══════════════════════════════════════════════════════════════════════════

  Stay _activeToStay(ActiveStay s) {
    final since = s.checkedInAt != null
        ? _time.format(s.checkedInAt!)
        : (s.checkIn != null ? _shortDate.format(s.checkIn!) : '');
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.active,
      subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
          ? AppTranslations.stayRoomNumber('${s.roomNumber}')
          : null,
      checkedInSince: since,
      nightsRemaining: s.nightsRemaining,
      checkInLabel: s.checkIn != null ? _fullDate.format(s.checkIn!) : '',
      checkOutLabel: s.checkOut != null ? _fullDate.format(s.checkOut!) : '',
    );
  }

  Stay _upcomingToStay(UpcomingStay s) {
    final total = double.tryParse(s.priceUsd) ?? 0;
    final perNight = s.nights > 0 ? total / s.nights : total;
    final days = s.checkIn?.difference(DateTime.now()).inDays;
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.upcoming,
      // The card force-unwraps subtitle/pricePerNight — never leave them null.
      subtitle: (s.roomNumber != null && s.roomNumber!.isNotEmpty)
          ? 'Carlton Hotel Damascus · Room ${s.roomNumber}'
          : 'Carlton Hotel Damascus',
      imagePath: 'assets/images/stay_room.png',
      checkInLabel: s.checkIn != null ? _fullDate.format(s.checkIn!) : '',
      checkOutLabel: s.checkOut != null ? _fullDate.format(s.checkOut!) : '',
      resCode: s.bookingCode,
      pricePerNight: AppTranslations.perNight(usd(perNight.toString())),
      isCancellable: s.isCancellable,
      nextCheckInDays: (days != null && days > 0) ? days : null,
    );
  }

  Stay _pastToStay(PastStay s) {
    final range = (s.checkIn != null && s.checkOut != null)
        ? '${_shortDate.format(s.checkIn!)} – '
              '${_shortDate.format(s.checkOut!)} · ${s.totalNights} nights'
        : '${s.totalNights} nights';
    return Stay(
      id: s.uuid,
      uuid: s.uuid,
      roomName: s.roomName.value,
      status: StayStatus.past,
      dateRangeLabel: range,
      totalCharged: usd(s.totalChargeUsd),
      resCode: s.bookingCode,
      hasReceipt: s.hasReceipt,
    );
  }

  ReceiptData _receiptToData(Stay stay, Receipt r) {
    final dateLabel =
        (r.reservation.checkIn != null && r.reservation.checkOut != null)
        ? '${_shortDate.format(r.reservation.checkIn!)} – '
              '${_shortDate.format(r.reservation.checkOut!)}'
        : (stay.dateRangeLabel ?? '');
    final balance = double.tryParse(r.balanceDueUsd) ?? 0;
    final paymentInfo = balance > 0
        ? AppTranslations.balanceDue(usd(r.balanceDueUsd))
        : (r.payments.isNotEmpty
              ? 'Payment processed · ${r.payments.first.method}'
              : 'Settled at the front desk');
    return ReceiptData(
      roomName: stay.roomName,
      dateLabel: dateLabel,
      resCode: r.reservation.bookingCode,
      lines: r.items
          .map((i) => (label: i.description, amount: usd(i.amountUsd)))
          .toList(),
      total: usd(r.folio.totalUsd),
      paymentInfo: paymentInfo,
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // Pure helpers (hermetically testable — no HTTP / GetX)
  // ══════════════════════════════════════════════════════════════════════════

  /// A USD decimal string off the API, rendered in the guest's currency.
  /// Was a third private copy of the same `'\$…'` builder.
  static String usd(String? amount) => MoneyFormat.usdString(amount);

  /// Copy for a failed cancel — a `reservation_state` (already checked in / past
  /// the cancellable window) gets specific wording; everything else is generic.
  static String cancelErrorMessage(String? code) => switch (code) {
    ErrorCodes.reservationState =>
      'This reservation can no longer be cancelled.',
    _ => 'Could not cancel this reservation. Please try again.',
  };

  // ══════════════════════════════════════════════════════════════════════════
  // Receipt
  // ══════════════════════════════════════════════════════════════════════════

  Future<void> showReceipt(Stay stay) async {
    if (!stay.hasReceipt) return;
    final res = await _api.get<Map<String, dynamic>>(
      path: '/stays/${stay.uuid}/receipt',
      showLoading: true,
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.statusCode != 200 || res.data == null) {
      CustomSnackbars.showError(message: AppTranslations.receiptLoadFailed);
      return;
    }
    final data = _receiptToData(stay, Receipt.fromJson(res.data!));
    CustomBottomSheet.show<void>(
      title: AppTranslations.receipt,
      subtitle: AppTranslations.receiptSubtitle(stay.roomName, data.dateLabel),
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

  /// Streams the PDF to a temp file. `downloadFile` bypasses the envelope and
  /// throws a raw [DioException], so it gets its own try/catch. Opening/sharing
  /// the saved file is a follow-up (no viewer dependency in pubspec yet).
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
      CustomSnackbars.showSuccess(
        message: AppTranslations.receiptSaved(savePath),
      );
    } on DioException catch (_) {
      if (isClosed) return;
      CustomSnackbars.showError(message: AppTranslations.receiptDownloadFailed);
    }
  }

  // ══════════════════════════════════════════════════════════════════════════
  // Cancel
  // ══════════════════════════════════════════════════════════════════════════

  void requestCancel(Stay stay) {
    CustomBottomSheet.show<void>(
      showClose: false,
      heightFactor: 0.5,
      child: CancelReservationSheet(stay: stay),
      actions: Row(
        spacing: 10,
        children: [
          Expanded(
            child: CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.pearlCream,
              foregroundColor: AppColors.inkBlack,
              onPressed: () => Get.back(),
              child: Text(AppTranslations.noKeep),
            ),
          ),
          Expanded(
            child: CustomFilledButton(
              width: double.infinity,
              backgroundColor: AppColors.brickRed,
              onPressed: () {
                Get.back();
                _cancelReservation(stay);
              },
              child: Text(AppTranslations.yesCancel),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _cancelReservation(Stay stay) async {
    final res = await _api.delete<dynamic>(
      path: '/reservations/${stay.uuid}',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.ok || res.isNoContent) {
      upcoming.removeWhere((s) => s.uuid == stay.uuid);
      // Cancelling may drop the guest's has_booking entitlement — resync from
      // the authoritative /me rather than guessing a flag flip.
      await MiddlewareService.find.checkToken();
      if (isClosed) return;
      CustomSnackbars.showSuccess(
        message: AppTranslations.reservationCancelled,
      );
    } else {
      final code = res.error?.errorCode;
      if (code == ErrorCodes.reservationState) {
        CustomSnackbars.showWarning(message: cancelErrorMessage(code));
      } else {
        CustomSnackbars.showError(message: cancelErrorMessage(code));
      }
    }
  }

  // ══════════════════════════════════════════════════════════════════════════
  // Active-stay actions
  // ══════════════════════════════════════════════════════════════════════════

  /// Jump to the Services tab (index 3), where in-stay requests are made.
  void requestService() => Get.find<MainController>().changeTab(3);

  /// Express checkout: confirm, then `POST /folio/approve` (approves the bill
  /// and flips the stay to `checked_out`).
  void expressCheckout() => CustomDialogs.showConfirmationDialog(
    title: AppTranslations.expressCheckout,
    message:
        '${AppTranslations.checkoutConfirmNow} '
        '${AppTranslations.checkoutStatementNote}',
    icon: 'assets/icons/act_checkout.svg',
    accentColor: AppColors.primary,
    onPressed: _confirmExpressCheckout,
  );

  Future<void> _confirmExpressCheckout() async {
    final res = await _api.post<Map<String, dynamic>>(
      path: '/folio/approve',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.ok) {
      if (res.data != null) {
        Folio.fromJson(res.data!); // parse the approved bill
      }
      CustomSnackbars.showSuccess(message: AppTranslations.checkoutComplete);
      // Stay is now checked_out — resync entitlements, then reload (→ null).
      await MiddlewareService.find.checkToken();
      if (isClosed) return;
      _loadActive();
    } else if (res.error?.errorCode == ErrorCodes.noActiveReservation) {
      CustomSnackbars.showInfo(message: AppTranslations.noActiveStayToCheckOut);
    } else {
      CustomSnackbars.showError(message: AppTranslations.checkoutFailed);
    }
  }

  /// "Book Again" / "Book" → start a fresh booking flow. Resets the shared
  /// draft first so a completed or abandoned attempt never leaks its guest
  /// or card details into the next one.
  void startBooking() {
    Get.find<BookingFlowController>().reset();
    // "Plan Your Stay" is the Book tab in the Main shell (no standalone route),
    // so switch to it (index 2) rather than navigating.
    Get.find<MainController>().changeTab(2);
  }
}
