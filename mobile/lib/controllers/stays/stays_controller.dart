import 'package:carlton/controllers/account/loyalty_controller.dart';
import 'package:carlton/controllers/home/home_controller.dart';
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
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/models/receipt.dart';
import 'package:carlton/models/reservation.dart';
import 'package:carlton/models/stay.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

part 'stays_cancel.dart';

part 'stays_receipt.dart';

part 'stays_mapping.dart';

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
  final Rxn<Stay> active = Rxn<Stay>();
  final RxBool activeLoading = true.obs;
  final RxBool activeError = false.obs;

  // ── Upcoming (plain array, not paginated) ──────────────────────────────────
  final RxList<Stay> upcoming = <Stay>[].obs;
  final RxBool upcomingLoading = true.obs;
  final RxBool upcomingError = false.obs;

  // ── Past uses the mixin's Rx items / loading / hasError + scrollController ──

  // Reused label formatters (money via [usd]).
  static DateFormat get _fullDate => DateFormat.yMMMd();
  static DateFormat get _shortDate => DateFormat.MMMd();
  static DateFormat get _time => DateFormat.jm();

  @override
  void onInit() {
    super.onInit();
    // Open on Upcoming — the tab with the actionable reservation.
    tabController = TabController(length: 3, vsync: this, initialIndex: 1);
    initPagination(_cancel);
    // A browsing guest has no stays and every call would be refused, which
    // the tabs would report as a connection error. The view shows a sign-in
    // prompt instead; the lists load once the guest signs in.
    var signedIn = MiddlewareService.find.isAuthenticated;
    if (signedIn) _loadAll();
    _authWorker = ever(MiddlewareService.find.guest, (guest) {
      // Only the signed-out → signed-in change reloads; a profile edit also
      // updates the guest and must not refetch every tab.
      if (guest != null && !signedIn) _loadAll();
      signedIn = guest != null;
    });
  }

  late final Worker _authWorker;

  void _loadAll() {
    _loadActive();
    _loadUpcoming();
    loadItems(_cancel); // past
  }

  @override
  void onClose() {
    _authWorker.dispose();
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
    if (res.hasData) {
      final stays = UpcomingStay.listFromJson(res.data);
      // `/stays/upcoming` has no `loyalty` block, so each booking's own
      // reservation (`GET /reservations/{uuid}`) says what it was paid with.
      // Per booking rather than the paged list (fixed at 15, newest first),
      // so an older upcoming stay never loses its note. A failure only drops
      // that note.
      final bookings = await Future.wait(
        stays.map(
          (s) => _api.get<Map<String, dynamic>>(
            path: '/reservations/${s.uuid}',
            showErrorDialog: false,
            cancelToken: _cancel,
          ),
        ),
      );
      if (isClosed) return;
      final rewards = <String, ReservationLoyalty>{
        for (final b in bookings)
          if (b.hasData)
            if (Reservation.fromJson(b.data!) case final r
                when r.loyalty != null)
              r.uuid: r.loyalty!,
      };
      upcoming.value = stays
          .map((s) => _upcomingToStay(s, rewards[s.uuid]))
          .toList();
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
    if (!res.hasData) return null;
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

  /// One line naming the rewards a booking used, or null for none. A room
  /// upgrade keeps the total, so it names the upgrade rather than an amount.
  static String? rewardsNote(ReservationLoyalty? rewards) {
    if (rewards == null || rewards.isReversed) return null;
    if (rewards.upgradeRequested) return AppTranslations.bookingRewardUpgrade;
    if (rewards.hasPoints) {
      return AppTranslations.bookingRewardPoints(
        rewards.pointsRedeemed,
        usd(rewards.pointsDiscountUsd),
      );
    }
    if (rewards.hasVoucher) {
      return AppTranslations.bookingRewardVoucher(
        rewards.voucherCode!,
        usd(rewards.voucherDiscountUsd),
      );
    }
    return null;
  }

  // ══════════════════════════════════════════════════════════════════════════
  // Pure helpers (hermetically testable — no HTTP / GetX)
  // ══════════════════════════════════════════════════════════════════════════

  /// "380.00" → "$380", "80.50" → "$80.50". Strips a trailing ".00" so whole
  /// amounts read cleanly. Mirrors `BookingFlowController.money`'s rule.
  static String usd(String? amount) {
    final v = double.tryParse(amount ?? '') ?? 0;
    final whole = v == v.roundToDouble();
    return '\$${whole ? v.toStringAsFixed(0) : v.toStringAsFixed(2)}';
  }

  /// Copy for a failed cancel — a `reservation_state` (already checked in / past
  /// the cancellable window) gets specific wording; everything else is generic.
  static String cancelErrorMessage(String? code) => switch (code) {
    ErrorCodes.reservationState => AppTranslations.notCancellable,
    _ => AppTranslations.cancelFailed,
  };

  // ══════════════════════════════════════════════════════════════════════════
  // Receipt
  // ══════════════════════════════════════════════════════════════════════════

  // ══════════════════════════════════════════════════════════════════════════
  // Cancel
  // ══════════════════════════════════════════════════════════════════════════

  /// Pure, for tests: the cancel message for a booking's reversed rewards.
  static String cancelledMessage(ReservationLoyalty? rewards) {
    if (rewards == null || !rewards.isReversed) {
      return AppTranslations.reservationCancelled;
    }
    if (rewards.hasPoints) {
      return AppTranslations.cancelledPointsReturned(rewards.pointsRedeemed);
    }
    if (rewards.hasVoucher) {
      return AppTranslations.cancelledVoucherRestored(rewards.voucherCode!);
    }
    return AppTranslations.reservationCancelled;
  }

  // ══════════════════════════════════════════════════════════════════════════
  // Active-stay actions
  // ══════════════════════════════════════════════════════════════════════════

  /// Jump to the Services tab (index 3), where in-stay requests are made.
  void requestService() => Get.find<MainController>().changeTab(3);

  /// Express checkout: confirm, then `POST /folio/approve` (approves the bill
  /// and flips the stay to `checked_out`).
  void expressCheckout() => CustomDialogs.showConfirmationDialog(
    title: AppTranslations.expressCheckoutCaps,
    message: AppTranslations.checkoutConfirmShort,
    icon: 'assets/icons/act_checkout.svg',
    accentColor: AppColors.primary,
    onPressed: confirmExpressCheckout,
  );

  /// `POST /folio/approve` — approves the bill and runs the real check-out.
  /// The one copy of this call: Home's checkout button confirms with its own
  /// wording, then lands here.
  Future<void> confirmExpressCheckout() async {
    final res = await _api.post<Map<String, dynamic>>(
      path: '/folio/approve',
      showErrorDialog: false,
      cancelToken: _cancel,
    );
    if (isClosed || res.isCancelled) return;
    if (res.ok) {
      CustomSnackbars.showSuccess(message: AppTranslations.checkoutComplete);
      // Stay is now checked_out — resync entitlements (Home re-resolves from
      // them), then reload the active tab (→ null) and the past list.
      await MiddlewareService.find.checkToken();
      if (isClosed) return;
      _loadActive();
      loadItems(_cancel);
      return;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.noActiveReservation:
        CustomSnackbars.showInfo(
          message: AppTranslations.noActiveStayToCheckOut,
        );
      // The guest's latest booking is not the checked-in stay; the desk has
      // to check this one out.
      case ErrorCodes.reservationState:
        CustomSnackbars.showWarning(message: AppTranslations.checkoutAtDesk);
      default:
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
