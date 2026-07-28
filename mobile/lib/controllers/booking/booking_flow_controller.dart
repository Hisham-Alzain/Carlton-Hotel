import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/quote.dart';
import 'package:carlton/models/reservation.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/views/book/room_details_sheet.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// Shared controller for the whole 5-step booking flow (Plan → Choose Room →
/// Add-Ons → Guest → Payment) plus the Room Details sheet. Registered once,
/// permanently, at app boot (see main.dart) instead of through a per-route
/// binding — every booking screen reads/writes and rebuilds off this same
/// instance via GetBuilder&lt;BookingFlowController&gt;. Stays a GetxController
/// (not GetxService) because GetxService doesn't support update()/GetBuilder
/// in this GetX version; the permanent, no-binding registration is what makes
/// it behave like a service. Being permanent (not tied to a route's fenix
/// lifecycle) also means [reset] is the only thing that clears it — callers
/// starting a new booking must call it explicitly (see
/// BookView/StaysController.startBooking), otherwise a completed booking's
/// guest/card details would carry into the next one.
class BookingFlowController extends GetxController {
  // Dates + guests
  final DateTime firstDay = DateUtils.dateOnly(DateTime.now());
  final DateTime lastDay = DateTime(2100, 12, 31);
  DateTime focusedDay = DateUtils.dateOnly(DateTime.now());
  DateTime? rangeStart = DateUtils.dateOnly(DateTime.now());
  DateTime? rangeEnd = DateUtils.dateOnly(
    DateTime.now(),
  ).add(const Duration(days: 1));
  int adults = 2;
  int children = 0;

  // Room + add-ons
  // Choose-Room list — fetched from GET /public/room-types (mapped to the
  // booking option). Add-ons stay demo (no backend concept).
  List<RoomOption> rooms = [];
  bool roomsLoading = false;
  final List<AddOn> addOns = DemoData.addOns;
  RoomOption? selectedRoom;
  final Set<String> selectedAddOnIds = {};

  /// True when the booking began from a room tapped on Home, so the room is
  /// already chosen and the Choose-Room step is skipped after picking dates.
  bool roomPreselected = false;

  /// Which photo of [selectedRoom] the Room Details carousel is showing.
  int roomImageIndex = 0;

  // Guest
  final firstNameCtrl = TextEditingController();
  final lastNameCtrl = TextEditingController();
  final emailCtrl = TextEditingController();
  final phone = PhoneFieldState();
  final specialRequestsCtrl = TextEditingController();

  // Payment
  PaymentMethod paymentMethod = PaymentMethod.card;
  final cardNumberCtrl = TextEditingController();
  final cardExpiryCtrl = TextEditingController();
  final cardCvvCtrl = TextEditingController();
  final cardNameCtrl = TextEditingController();
  final promoCtrl = TextEditingController();
  bool promoApplied = false;

  // ── Pricing (real: GET /public/quote) ─────────────────────────────────────
  /// The server-priced quote for the current room + dates (+ promo). Null until
  /// fetched, or for a pure-demo room (no uuid) where we fall back to an
  /// estimate. The breakdown/total read from this, not a client tax/promo calc.
  Quote? quote;
  bool quoteLoading = false;

  /// Inline promo error (e.g. `invalid_promo`), shown under the promo field.
  String? promoError;

  /// True while `POST /reservations` is in flight (disables the confirm CTA).
  bool isConfirming = false;

  /// Set on a successful `POST /reservations`; shown on the Confirmed screen.
  String? confirmationCode;

  /// The full reservation returned by the last successful booking.
  Reservation? lastReservation;

  // ── Cross-screen derived values ─────────────────────────────────────────
  int get nights {
    if (rangeStart == null || rangeEnd == null) return 2;
    final n = rangeEnd!.difference(rangeStart!).inDays;
    return n < 1 ? 1 : n;
  }

  bool get hasDates => rangeStart != null && rangeEnd != null;

  /// "Aug 14 → Aug 16" — the date range without the nights suffix.
  String get dateRange {
    if (rangeStart == null || rangeEnd == null) return 'Select your dates';
    final f = DateFormat('MMM d');
    return '${f.format(rangeStart!)} → ${f.format(rangeEnd!)}';
  }

  /// "Aug 14 → Aug 16 · 2 nights" — used by the Plan footer and Choose Room
  /// context bar (Figma shows the nights there).
  String get dateSummary =>
      hasDates ? '$dateRange · $nights nights' : dateRange;

  String get guestSummary {
    final a = '$adults Adult ${adults == 1 ? '' : 's'}';
    if (children == 0) return a;
    return '$a, $children Child ${children == 1 ? '' : 'ren'}';
  }

  /// "Aug 14 → Aug 16 · \$280/night" — the Add-Ons summary tile (Figma omits
  /// the nights here, unlike [dateSummary]).
  String get roomDetailSummary {
    if (selectedRoom == null) return dateSummary;
    return '$dateRange · \$${selectedRoom!.pricePerNight}/night';
  }

  /// Fallback subtotal (room nightly × nights) used only for a pure-demo room
  /// with no uuid, where the quote endpoint can't be called.
  int get roomSubtotalEstimate => (selectedRoom?.pricePerNight ?? 0) * nights;

  /// "$580" — strips a trailing ".00" so whole amounts read cleanly.
  String money(double v) {
    final whole = v == v.roundToDouble();
    return '\$${whole ? v.toStringAsFixed(0) : v.toStringAsFixed(2)}';
  }

  /// Nights the price is based on — the quote's own count when priced, else the
  /// locally-derived nights.
  int get displayNights => quote?.nights ?? nights;

  /// Room-subtotal line: the quote's subtotal when priced, else the estimate.
  String get subtotalDisplay =>
      quote != null ? money(quote!.subtotalUsd) : '\$$roomSubtotalEstimate';

  /// True when a promo actually reduced the priced total (drives the discount
  /// row in the breakdown).
  bool get hasDiscount => quote?.hasPromo ?? false;

  String get discountDisplay =>
      quote != null ? '-${money(quote!.discountUsd)}' : '';

  /// Grand total shown on the summary card / confirm CTA: the real quote total
  /// when priced, else the estimate.
  String get totalDisplay =>
      quote != null ? money(quote!.totalUsd) : '\$$roomSubtotalEstimate';

  // ── Step 1 — Plan Your Stay ──────────────────────────────────────────────
  void onRangeSelected(DateTime? start, DateTime? end, DateTime focused) {
    focusedDay = focused;
    // A hotel stay must be at least one night: if both endpoints land on the
    // same day, keep it as the check-in and wait for a later check-out.
    if (start != null && end != null && !end.isAfter(start)) {
      rangeStart = start;
      rangeEnd = null;
    } else {
      rangeStart = start;
      rangeEnd = end;
    }
    update();
  }

  void onPageChanged(DateTime focused) => focusedDay = focused;

  void setAdults(int v) {
    adults = v;
    update();
  }

  void setChildren(int v) {
    children = v;
    update();
  }

  /// Tapping a check-in/check-out box clears the range so the calendar is
  /// ready for a fresh pick.
  void restartDateSelection() {
    rangeStart = null;
    rangeEnd = null;
    update();
  }

  void searchRooms() {
    if (!hasDates) {
      CustomSnackbars.showInfo(message: 'Select your dates first');
      return;
    }
    // Room already chosen on Home → skip Choose-Room, go straight to add-ons.
    if (roomPreselected && selectedRoom != null) {
      Get.toNamed(Routes.addOns);
      return;
    }
    Get.toNamed(Routes.chooseRoom);
    loadRooms();
  }

  /// Loads the Choose-Room list from `GET /public/room-types`, mapped to the
  /// booking option. GetBuilder rebuilds when it lands.
  Future<void> loadRooms() async {
    roomsLoading = true;
    rooms = [];
    update();
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/room-types',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 200 && res.data != null) {
      rooms = res.data!
          .whereType<Map<String, dynamic>>()
          .map(RoomType.fromJson)
          .map(RoomOption.fromRoomType)
          .toList();
    }
    roomsLoading = false;
    update();
  }

  // ── Step 2 — Choose Your Room + Room Details sheet ──────────────────────
  void setRoomImage(int index) {
    roomImageIndex = index;
    update();
  }

  void openRoomDetails(RoomOption room) {
    roomImageIndex = 0;
    update();
    CustomBottomSheet.show<void>(
      // The content scrolls itself and carries its own close button.
      scrollable: false,
      showClose: false,
      child: RoomDetailsSheet(room: room),
    );
  }

  /// Entry from the Home room list: open the full-screen details page.
  void openRoomDetailsScreen(RoomOption room) {
    roomImageIndex = 0;
    update();
    Get.toNamed(Routes.roomDetails, arguments: room);
  }

  /// From a listing tap (Home/Discover): fetch the real room-type detail
  /// (`GET /public/room-types/{uuid}`) and open it.
  Future<void> openRoomListing(String uuid) async {
    if (uuid.isEmpty) return;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/room-types/$uuid',
      showLoading: true,
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 200 && res.data != null) {
      openRoomDetailsScreen(
        RoomOption.fromRoomType(RoomType.fromJson(res.data!)),
      );
    } else {
      CustomSnackbars.showError(message: 'Could not load this room.');
    }
  }

  /// "Select This Room" from the full-screen details page — start a fresh
  /// booking with this room preselected (carrying its real `room_type_uuid`).
  /// Resets first (like every booking entry) so a prior attempt's guest/card/
  /// add-on data never carries over.
  void beginBookingWithRoom(RoomOption room) {
    reset();
    selectedRoom = room;
    roomPreselected = true;
    update();
    // "Plan Your Stay" is the Book tab in the Main shell (no standalone route),
    // so pop back to the shell and switch to it (index 2).
    Get.until((r) => r.isFirst);
    Get.find<MainController>().changeTab(2);
  }

  void selectRoom(RoomOption room) {
    selectedRoom = room;
    update();
    Get.toNamed(Routes.addOns);
  }

  // ── Step 3 — Add-Ons ─────────────────────────────────────────────────────
  String get addOnsCtaLabel {
    final n = selectedAddOnIds.length;
    return n == 0
        ? 'Skip — No Extras'
        : 'Continue with $n extra${n == 1 ? '' : 's'}';
  }

  void toggleAddOn(String id) {
    if (!selectedAddOnIds.remove(id)) selectedAddOnIds.add(id);
    update();
  }

  void continueFromAddOns() => Get.toNamed(Routes.guestDetails);

  // ── Step 4 — Guest Details ───────────────────────────────────────────────
  final guestFormKey = GlobalKey<FormState>();

  void continueFromGuest() {
    // if (!guestFormKey.currentState!.validate()) return;
    Get.toNamed(Routes.payment);
    // Price the stay for the Payment summary + Review breakdown. Fire-and-forget:
    // the GetBuilder rebuilds when the quote lands.
    _fetchQuote(promo: promoApplied ? promoCtrl.text.trim() : null);
  }

  // ── Step 5 — Payment ─────────────────────────────────────────────────────
  bool get isCardComplete =>
      cardNumberCtrl.text.trim().isNotEmpty &&
      cardExpiryCtrl.text.trim().isNotEmpty &&
      cardCvvCtrl.text.trim().isNotEmpty &&
      cardNameCtrl.text.trim().isNotEmpty;

  /// The Review Booking CTA is only enabled once payment details are valid.
  bool get canReviewBooking =>
      paymentMethod != PaymentMethod.card || isCardComplete;

  void selectPaymentMethod(PaymentMethod m) {
    paymentMethod = m;
    update();
  }

  void onPaymentFieldChanged() => update();

  /// Re-prices the stay with the entered promo. A bad/expired code now surfaces
  /// `invalid_promo` inline (via [promoError]) instead of always "succeeding".
  Future<void> applyPromo() async {
    final code = promoCtrl.text.trim();
    if (code.isEmpty) {
      CustomSnackbars.showInfo(message: 'Enter a promo code first');
      return;
    }
    await _fetchQuote(promo: code);
    if (promoError == null && hasDiscount) {
      promoApplied = true;
      CustomSnackbars.showSuccess(message: 'Promo code "$code" applied');
    }
    update();
  }

  String _fmtDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  /// Fetches the server price for the current room + dates (+ optional promo).
  /// A pure-demo room (no uuid) can't be quoted, so the estimate stands in.
  Future<void> _fetchQuote({String? promo}) async {
    final uuid = selectedRoom?.uuid ?? '';
    if (uuid.isEmpty || !hasDates) return;
    quoteLoading = true;
    update();
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/quote',
      queryParameters: {
        'room_type_uuid': uuid,
        'check_in': _fmtDate(rangeStart!),
        'check_out': _fmtDate(rangeEnd!),
        if (promo != null && promo.isNotEmpty) 'promo_code': promo,
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    quoteLoading = false;
    if (res.statusCode == 200 && res.data != null) {
      quote = Quote.fromJson(res.data!);
      promoError = null;
    } else if (res.error?.errorCode == ErrorCodes.invalidPromo) {
      // Keep the prior (un-promo) quote so the price doesn't vanish; just flag it.
      promoError = 'That promo code is invalid or expired.';
      promoApplied = false;
    }
    update();
  }

  /// "Credit Card ••••1234" / "Apple Pay" / … for the Review summary row.
  String get paymentMethodDisplay {
    if (paymentMethod != PaymentMethod.card) return paymentMethod.label;
    final digits = cardNumberCtrl.text.replaceAll(RegExp(r'\D'), '');
    final last4 = digits.length >= 4 ? digits.substring(digits.length - 4) : '';
    return last4.isEmpty ? 'Credit Card' : 'Credit Card ••••$last4';
  }

  /// The backend `payment_method` value for the selected option, or null when
  /// it can't be submitted yet — there is no real card/wallet gateway, so only
  /// Pay-at-Hotel maps (→ `on_arrival`). See decision #1.
  String? get paymentApiValue => switch (paymentMethod) {
    PaymentMethod.payAtHotel => 'on_arrival',
    PaymentMethod.card ||
    PaymentMethod.applePay ||
    PaymentMethod.googlePay => null,
  };

  bool get isPaymentSubmittable => paymentApiValue != null;

  /// Review CTA copy — "Confirm & Pay \$X" for priced methods, "Confirm Booking"
  /// when paying at the hotel.
  String get confirmCtaLabel => paymentMethod == PaymentMethod.payAtHotel
      ? 'Confirm Booking'
      : 'Confirm & Pay $totalDisplay';

  void reviewBooking() {
    if (!canReviewBooking) return;
    Get.toNamed(Routes.reviewBooking);
  }

  /// Confirms via `POST /reservations` (tier-2, token identity). Card/wallet
  /// methods have no gateway yet, so they're blocked here with a clear message
  /// rather than submitting. On success the guest gains a booking and the real
  /// `booking_code` shows on the Confirmed screen.
  Future<void> confirmBooking() async {
    if (isConfirming) return;
    // A one-step reservation needs a guest token (tier-2). A guest browsing
    // without an account gets sent to sign-in rather than a bare 401 failure.
    if (!MiddlewareService.find.isAuthenticated) {
      CustomSnackbars.showInfo(
        message: 'Please sign in to complete your booking.',
      );
      Get.toNamed(Routes.signIn);
      return;
    }
    final apiMethod = paymentApiValue;
    if (apiMethod == null) {
      CustomSnackbars.showInfo(
        message:
            "Card & wallet payments aren't available yet — choose Pay at Hotel "
            'to confirm your booking.',
      );
      return;
    }
    final uuid = selectedRoom?.uuid ?? '';
    if (uuid.isEmpty || !hasDates) {
      CustomSnackbars.showError(message: 'Select a room and your dates first.');
      return;
    }

    isConfirming = true;
    update();
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/reservations',
      data: {
        'room_type_uuid': uuid,
        'check_in': _fmtDate(rangeStart!),
        'check_out': _fmtDate(rangeEnd!),
        'payment_method': apiMethod,
        if (promoApplied && promoCtrl.text.trim().isNotEmpty)
          'promo_code': promoCtrl.text.trim(),
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    isConfirming = false;

    if (res.statusCode == 201 && res.data != null) {
      final reservation = Reservation.fromJson(res.data!);
      lastReservation = reservation;
      confirmationCode = reservation.bookingCode;
      // The guest now has a booking — flip the app's active-booking entitlement
      // so Home/Services reflect it without a refetch.
      final guest = MiddlewareService.find.guest.value;
      if (guest != null) {
        MiddlewareService.find.updateGuest(guest.copyWith(hasBooking: true));
      }
      update();
      Get.toNamed(Routes.bookingConfirmed);
      return;
    }

    update();
    final message = switch (res.error?.errorCode) {
      ErrorCodes.noAvailability =>
        'Those dates just sold out — please try different dates.',
      ErrorCodes.invalidPromo => 'That promo code is invalid or expired.',
      _ => res.error?.message ?? 'We couldn\'t complete your booking.',
    };
    CustomSnackbars.showError(message: message);
  }

  /// Copies the confirmation code to the clipboard (from the confirmed screen).
  void copyConfirmationCode() {
    final code = confirmationCode;
    if (code == null) return;
    Clipboard.setData(ClipboardData(text: code));
    CustomSnackbars.showSuccess(message: 'Code copied');
  }

  /// "View My Stays" from the confirmation screen — back to the shell on the
  /// Stays tab.
  void viewMyStays() {
    Get.until((r) => r.isFirst);
    Get.find<MainController>().changeTab(1);
  }

  /// Clears every field back to its starting value — call before entering the
  /// flow for a new booking (not after finishing one), so "Book Again" never
  /// shows a previous attempt's guest/card details.
  void reset() {
    final today = DateUtils.dateOnly(DateTime.now());
    focusedDay = today;
    rangeStart = today;
    rangeEnd = today.add(const Duration(days: 1));
    adults = 2;
    children = 0;
    selectedRoom = null;
    roomPreselected = false;
    roomImageIndex = 0;
    selectedAddOnIds.clear();
    firstNameCtrl.clear();
    lastNameCtrl.clear();
    emailCtrl.clear();
    phone.reset();
    specialRequestsCtrl.clear();
    paymentMethod = PaymentMethod.card;
    cardNumberCtrl.clear();
    cardExpiryCtrl.clear();
    cardCvvCtrl.clear();
    cardNameCtrl.clear();
    promoCtrl.clear();
    promoApplied = false;
    promoError = null;
    quote = null;
    quoteLoading = false;
    isConfirming = false;
    confirmationCode = null;
    lastReservation = null;
    // Rebuild live listeners (the Book tab's plan editor) after a reset; other
    // callers navigate away right after, so this is harmless for them.
    update();
  }

  @override
  void onClose() {
    for (final c in [
      firstNameCtrl,
      lastNameCtrl,
      emailCtrl,
      specialRequestsCtrl,
      cardNumberCtrl,
      cardExpiryCtrl,
      cardCvvCtrl,
      cardNameCtrl,
      promoCtrl,
    ]) {
      c.dispose();
    }
    phone.dispose();
    super.onClose();
  }
}
