import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
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
/// instance via its Rx state. Being permanent (not tied to a route's fenix
/// lifecycle) also means [reset] is the only thing that clears it — callers
/// starting a new booking must call it explicitly (see
/// BookView/StaysController.startBooking), otherwise a completed booking's
/// guest/card details would carry into the next one.
class BookingFlowController extends GetxController {
  // Dates + guests
  final DateTime firstDay = DateUtils.dateOnly(DateTime.now());
  final DateTime lastDay = DateTime(2100, 12, 31);
  final Rx<DateTime> focusedDay = Rx(DateUtils.dateOnly(DateTime.now()));
  final Rx<DateTime?> rangeStart = Rx<DateTime?>(
    DateUtils.dateOnly(DateTime.now()),
  );
  final Rx<DateTime?> rangeEnd = Rx<DateTime?>(
    DateUtils.dateOnly(DateTime.now()).add(const Duration(days: 1)),
  );
  final RxInt adults = 2.obs;
  final RxInt children = 0.obs;

  // Room + add-ons
  // Choose-Room list — fetched from GET /public/room-types (mapped to the
  // booking option). Add-ons stay demo (no backend concept).
  final RxList<RoomOption> rooms = <RoomOption>[].obs;
  final RxBool roomsLoading = false.obs;
  // No backend add-ons catalog exists yet.
  final List<AddOn> addOns = const [];
  final Rx<RoomOption?> selectedRoom = Rx<RoomOption?>(null);
  final RxSet<String> selectedAddOnIds = <String>{}.obs;

  /// True when the booking began from a room tapped on Home, so the room is
  /// already chosen and the Choose-Room step is skipped after picking dates.
  final RxBool roomPreselected = false.obs;

  /// Which photo of [selectedRoom] the Room Details carousel is showing.
  final RxInt roomImageIndex = 0.obs;

  // Guest
  final firstNameCtrl = TextEditingController();
  final lastNameCtrl = TextEditingController();
  final emailCtrl = TextEditingController();
  final phone = PhoneFieldState();
  final specialRequestsCtrl = TextEditingController();

  // Payment
  final Rx<PaymentMethod> paymentMethod = PaymentMethod.card.obs;
  final cardNumberCtrl = TextEditingController();
  final cardExpiryCtrl = TextEditingController();
  final cardCvvCtrl = TextEditingController();
  final cardNameCtrl = TextEditingController();
  final promoCtrl = TextEditingController();
  final RxBool promoApplied = false.obs;

  // ── Pricing (real: GET /public/quote) ─────────────────────────────────────
  /// The server-priced quote for the current room + dates (+ promo). Null until
  /// fetched, or for a pure-demo room (no uuid) where we fall back to an
  /// estimate. The breakdown/total read from this, not a client tax/promo calc.
  final Rx<Quote?> quote = Rx<Quote?>(null);
  final RxBool quoteLoading = false.obs;

  /// Inline promo error (e.g. `invalid_promo`), shown under the promo field.
  final RxnString promoError = RxnString();

  /// True while `POST /reservations` is in flight (disables the confirm CTA).
  final RxBool isConfirming = false.obs;

  /// Set on a successful `POST /reservations`; shown on the Confirmed screen.
  final RxnString confirmationCode = RxnString();

  /// The full reservation returned by the last successful booking.
  final Rx<Reservation?> lastReservation = Rx<Reservation?>(null);

  // ── Cross-screen derived values ─────────────────────────────────────────
  int get nights {
    if (rangeStart.value == null || rangeEnd.value == null) return 2;
    final n = rangeEnd.value!.difference(rangeStart.value!).inDays;
    return n < 1 ? 1 : n;
  }

  bool get hasDates => rangeStart.value != null && rangeEnd.value != null;

  /// "Aug 14 → Aug 16" — the date range without the nights suffix.
  String get dateRange {
    if (rangeStart.value == null || rangeEnd.value == null) {
      return AppTranslations.selectYourDates;
    }
    final f = DateFormat('MMM d');
    return '${f.format(rangeStart.value!)} → ${f.format(rangeEnd.value!)}';
  }

  /// "Aug 14 → Aug 16 · 2 nights" — used by the Plan footer and Choose Room
  /// context bar (Figma shows the nights there).
  String get dateSummary => hasDates
      ? '$dateRange · ${AppTranslations.nightsCount(nights)}'
      : dateRange;

  /// Built from per-form keys rather than by appending an English "s"/"ren"
  /// to a singular — that only ever produced correct plurals in English.
  String get guestSummary {
    final a = AppTranslations.adultsCount(adults.value);
    if (children.value == 0) return a;
    return '$a, ${AppTranslations.childrenCount(children.value)}';
  }

  /// "Aug 14 → Aug 16 · \$280/night" — the Add-Ons summary tile (Figma omits
  /// the nights here, unlike [dateSummary]).
  String get roomDetailSummary {
    final room = selectedRoom.value;
    if (room == null) return dateSummary;
    return '$dateRange · '
        '${AppTranslations.perNight(money(room.pricePerNight.toDouble()))}';
  }

  /// Fallback subtotal (room nightly × nights) used only for a pure-demo room
  /// with no uuid, where the quote endpoint can't be called.
  int get roomSubtotalEstimate =>
      (selectedRoom.value?.pricePerNight ?? 0) * nights;

  /// A USD amount in the guest's selected currency. Delegates to the one
  /// formatter — this used to be a private `'\$…'` builder, one of three
  /// copies, none of which honoured the currency picker.
  String money(double v) => v.formatPrice();

  /// Nights the price is based on — the quote's own count when priced, else the
  /// locally-derived nights.
  int get displayNights => quote.value?.nights ?? nights;

  /// Room-subtotal line: the quote's subtotal when priced, else the estimate.
  String get subtotalDisplay => quote.value != null
      ? money(quote.value!.subtotalUsd)
      : money(roomSubtotalEstimate.toDouble());

  /// True when a promo actually reduced the priced total (drives the discount
  /// row in the breakdown).
  bool get hasDiscount => quote.value?.hasPromo ?? false;

  String get discountDisplay =>
      quote.value != null ? '-${money(quote.value!.discountUsd)}' : '';

  /// Grand total shown on the summary card / confirm CTA: the real quote total
  /// when priced, else the estimate.
  String get totalDisplay => quote.value != null
      ? money(quote.value!.totalUsd)
      : money(roomSubtotalEstimate.toDouble());

  // ── Step 1 — Plan Your Stay ──────────────────────────────────────────────
  void onRangeSelected(DateTime? start, DateTime? end, DateTime focused) {
    focusedDay.value = focused;
    // A hotel stay must be at least one night: if both endpoints land on the
    // same day, keep it as the check-in and wait for a later check-out.
    if (start != null && end != null && !end.isAfter(start)) {
      rangeStart.value = start;
      rangeEnd.value = null;
    } else {
      rangeStart.value = start;
      rangeEnd.value = end;
    }
  }

  void onPageChanged(DateTime focused) => focusedDay.value = focused;

  void setAdults(int v) {
    adults.value = v;
  }

  void setChildren(int v) {
    children.value = v;
  }

  /// Tapping a check-in/check-out box clears the range so the calendar is
  /// ready for a fresh pick.
  void restartDateSelection() {
    rangeStart.value = null;
    rangeEnd.value = null;
  }

  void searchRooms() {
    if (!hasDates) {
      CustomSnackbars.showInfo(message: AppTranslations.selectYourDatesFirst);
      return;
    }
    // Room already chosen on Home → skip Choose-Room, go straight to add-ons.
    if (roomPreselected.value && selectedRoom.value != null) {
      Get.toNamed(Routes.addOns);
      return;
    }
    Get.toNamed(Routes.chooseRoom);
    loadRooms();
  }

  /// Loads the Choose-Room list from `GET /public/room-types`, mapped to the
  /// booking option.
  Future<void> loadRooms() async {
    roomsLoading.value = true;
    rooms.clear();
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/room-types',
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 200 && res.data != null) {
      rooms.assignAll(
        res.data!
            .whereType<Map<String, dynamic>>()
            .map(RoomType.fromJson)
            .map(RoomOption.fromRoomType),
      );
    }
    roomsLoading.value = false;
  }

  // ── Step 2 — Choose Your Room + Room Details sheet ──────────────────────
  void setRoomImage(int index) {
    roomImageIndex.value = index;
  }

  void openRoomDetails(RoomOption room) {
    roomImageIndex.value = 0;
    CustomBottomSheet.show<void>(
      // The content scrolls itself and carries its own close button.
      scrollable: false,
      showClose: false,
      child: RoomDetailsSheet(room: room),
    );
  }

  /// Entry from the Home room list: open the full-screen details page.
  void openRoomDetailsScreen(RoomOption room) {
    roomImageIndex.value = 0;
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
      CustomSnackbars.showError(message: AppTranslations.roomLoadFailed);
    }
  }

  /// "Select This Room" from the full-screen details page — start a fresh
  /// booking with this room preselected (carrying its real `room_type_uuid`).
  /// Resets first (like every booking entry) so a prior attempt's guest/card/
  /// add-on data never carries over.
  void beginBookingWithRoom(RoomOption room) {
    reset();
    selectedRoom.value = room;
    roomPreselected.value = true;
    // "Plan Your Stay" is the Book tab in the Main shell (no standalone route),
    // so pop back to the shell and switch to it (index 2).
    Get.until((r) => r.isFirst);
    Get.find<MainController>().changeTab(2);
  }

  void selectRoom(RoomOption room) {
    selectedRoom.value = room;
    Get.toNamed(Routes.addOns);
  }

  // ── Step 3 — Add-Ons ─────────────────────────────────────────────────────
  String get addOnsCtaLabel {
    final n = selectedAddOnIds.length;
    return n == 0
        ? AppTranslations.skipNoExtras
        : 'Continue with $n extra${n == 1 ? '' : 's'}';
  }

  /// USD total of the selected extras. The Add-Ons footer previously rendered
  /// a hardcoded `+\$30` regardless of what was ticked.
  int get selectedAddOnsTotalUsd => addOns
      .where((a) => selectedAddOnIds.contains(a.id))
      .fold(0, (sum, a) => sum + a.price);

  void toggleAddOn(String id) {
    if (!selectedAddOnIds.remove(id)) selectedAddOnIds.add(id);
  }

  void continueFromAddOns() => Get.toNamed(Routes.guestDetails);

  // ── Step 4 — Guest Details ───────────────────────────────────────────────
  final guestFormKey = GlobalKey<FormState>();

  void continueFromGuest() {
    // if (!guestFormKey.currentState!.validate()) return;
    Get.toNamed(Routes.payment);
    // Price the stay for the Payment summary + Review breakdown. Fire-and-forget.
    _fetchQuote(promo: promoApplied.value ? promoCtrl.text.trim() : null);
  }

  // ── Step 5 — Payment ─────────────────────────────────────────────────────
  /// The card fields live on plain [TextEditingController]s, so
  /// [isCardComplete] has nothing reactive of its own to read. This tick — bumped
  /// by [onPaymentFieldChanged] on every keystroke — is what an `Obx` around
  /// [isCardComplete]/[canReviewBooking] actually subscribes to.
  final RxInt _cardFieldsTick = 0.obs;

  bool get isCardComplete {
    // ignore: unused_local_variable
    final tick = _cardFieldsTick.value;
    return cardNumberCtrl.text.trim().isNotEmpty &&
        cardExpiryCtrl.text.trim().isNotEmpty &&
        cardCvvCtrl.text.trim().isNotEmpty &&
        cardNameCtrl.text.trim().isNotEmpty;
  }

  /// The Review Booking CTA is only enabled once payment details are valid.
  bool get canReviewBooking =>
      paymentMethod.value != PaymentMethod.card || isCardComplete;

  void selectPaymentMethod(PaymentMethod m) {
    paymentMethod.value = m;
  }

  void onPaymentFieldChanged() => _cardFieldsTick.value++;

  /// Re-prices the stay with the entered promo. A bad/expired code now surfaces
  /// `invalid_promo` inline (via [promoError]) instead of always "succeeding".
  Future<void> applyPromo() async {
    final code = promoCtrl.text.trim();
    if (code.isEmpty) {
      CustomSnackbars.showInfo(message: AppTranslations.promoCodeFirst);
      return;
    }
    await _fetchQuote(promo: code);
    if (promoError.value == null && hasDiscount) {
      promoApplied.value = true;
      CustomSnackbars.showSuccess(
        message: AppTranslations.promoCodeApplied(code),
      );
    }
  }

  String _fmtDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  /// Fetches the server price for the current room + dates (+ optional promo).
  /// A pure-demo room (no uuid) can't be quoted, so the estimate stands in.
  Future<void> _fetchQuote({String? promo}) async {
    final uuid = selectedRoom.value?.uuid ?? '';
    if (uuid.isEmpty || !hasDates) return;
    quoteLoading.value = true;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/quote',
      queryParameters: {
        'room_type_uuid': uuid,
        'check_in': _fmtDate(rangeStart.value!),
        'check_out': _fmtDate(rangeEnd.value!),
        if (promo != null && promo.isNotEmpty) 'promo_code': promo,
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    quoteLoading.value = false;
    if (res.statusCode == 200 && res.data != null) {
      quote.value = Quote.fromJson(res.data!);
      promoError.value = null;
    } else if (res.error?.errorCode == ErrorCodes.invalidPromo) {
      // Keep the prior (un-promo) quote so the price doesn't vanish; just flag it.
      promoError.value = 'That promo code is invalid or expired.';
      promoApplied.value = false;
    }
  }

  /// "Credit Card ••••1234" / "Apple Pay" / … for the Review summary row.
  String get paymentMethodDisplay {
    if (paymentMethod.value != PaymentMethod.card) {
      return paymentMethod.value.label;
    }
    final digits = cardNumberCtrl.text.replaceAll(RegExp(r'\D'), '');
    final last4 = digits.length >= 4 ? digits.substring(digits.length - 4) : '';
    return last4.isEmpty
        ? AppTranslations.creditCard
        : AppTranslations.creditCardMasked(last4);
  }

  /// The backend `payment_method` value for the selected option, or null when
  /// it can't be submitted yet — there is no real card/wallet gateway, so only
  /// Pay-at-Hotel maps (→ `on_arrival`). See decision #1.
  String? get paymentApiValue => switch (paymentMethod.value) {
    PaymentMethod.payAtHotel => 'on_arrival',
    PaymentMethod.card ||
    PaymentMethod.applePay ||
    PaymentMethod.googlePay => null,
  };

  bool get isPaymentSubmittable => paymentApiValue != null;

  /// Review CTA copy — "Confirm & Pay \$X" for priced methods, "Confirm Booking"
  /// when paying at the hotel.
  String get confirmCtaLabel => paymentMethod.value == PaymentMethod.payAtHotel
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
    if (isConfirming.value) return;
    // A one-step reservation needs a guest token (tier-2). A guest browsing
    // without an account gets sent to sign-in rather than a bare 401 failure.
    if (!MiddlewareService.find.isAuthenticated) {
      CustomSnackbars.showInfo(message: AppTranslations.signInToBook);
      Get.toNamed(Routes.signIn);
      return;
    }
    final apiMethod = paymentApiValue;
    if (apiMethod == null) {
      CustomSnackbars.showInfo(message: AppTranslations.walletComingSoon);
      return;
    }
    final uuid = selectedRoom.value?.uuid ?? '';
    if (uuid.isEmpty || !hasDates) {
      CustomSnackbars.showError(message: AppTranslations.selectRoomAndDates);
      return;
    }

    isConfirming.value = true;
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/reservations',
      data: {
        'room_type_uuid': uuid,
        'check_in': _fmtDate(rangeStart.value!),
        'check_out': _fmtDate(rangeEnd.value!),
        'payment_method': apiMethod,
        if (promoApplied.value && promoCtrl.text.trim().isNotEmpty)
          'promo_code': promoCtrl.text.trim(),
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    isConfirming.value = false;

    if (res.statusCode == 201 && res.data != null) {
      final reservation = Reservation.fromJson(res.data!);
      lastReservation.value = reservation;
      confirmationCode.value = reservation.bookingCode;
      // The guest now has a booking — flip the app's active-booking entitlement
      // so Home/Services reflect it without a refetch.
      final guest = MiddlewareService.find.guest.value;
      if (guest != null) {
        MiddlewareService.find.updateGuest(guest.copyWith(hasBooking: true));
      }
      Get.toNamed(Routes.bookingConfirmed);
      return;
    }

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
    final code = confirmationCode.value;
    if (code == null) return;
    Clipboard.setData(ClipboardData(text: code));
    CustomSnackbars.showSuccess(message: AppTranslations.copied);
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
    focusedDay.value = today;
    rangeStart.value = today;
    rangeEnd.value = today.add(const Duration(days: 1));
    adults.value = 2;
    children.value = 0;
    selectedRoom.value = null;
    roomPreselected.value = false;
    roomImageIndex.value = 0;
    selectedAddOnIds.clear();
    firstNameCtrl.clear();
    lastNameCtrl.clear();
    emailCtrl.clear();
    phone.reset();
    specialRequestsCtrl.clear();
    paymentMethod.value = PaymentMethod.card;
    cardNumberCtrl.clear();
    cardExpiryCtrl.clear();
    cardCvvCtrl.clear();
    cardNameCtrl.clear();
    promoCtrl.clear();
    promoApplied.value = false;
    promoError.value = null;
    quote.value = null;
    quoteLoading.value = false;
    isConfirming.value = false;
    confirmationCode.value = null;
    lastReservation.value = null;
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
