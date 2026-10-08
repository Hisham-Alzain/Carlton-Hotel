import 'package:uuid/uuid.dart';
import 'package:carlton/models/loyalty.dart';
import 'package:carlton/models/api/api_exception.dart';
import 'package:carlton/extensions/points_extension.dart';
import 'dart:convert';
import 'dart:async';
import 'package:carlton/constants/hotel_time.dart';
import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/extensions/price_extension.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/controllers/main/main_controller.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/controllers/stays/stays_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_country_code_picker.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/api/api_response.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/localized.dart';
import 'package:carlton/models/quote.dart';
import 'package:carlton/models/reservation.dart';
import 'package:carlton/models/room_type.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:carlton/views/book/room_details_sheet.dart';
import 'package:dio/dio.dart';
import 'package:carlton/customWidgets/custom_dialogs.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

part 'booking_flow_confirm.dart';

part 'booking_flow_addons.dart';

part 'booking_flow_rooms.dart';

part 'booking_flow_loyalty.dart';

class BookingFlowController extends GetxController
    with PaginatedControllerMixin<RoomOption> {
  final CancelToken _cancelToken = CancelToken();

  // Dates + guests
  final DateTime firstDay = DateUtils.dateOnly(DateTime.now());
  final DateTime lastDay = DateTime(2100, 12, 31);
  DateTime focusedDay = DateUtils.dateOnly(DateTime.now());
  final Rxn<DateTime> rangeStart = Rxn<DateTime>(
    DateUtils.dateOnly(DateTime.now()),
  );
  final Rxn<DateTime> rangeEnd = Rxn<DateTime>(
    DateUtils.dateOnly(DateTime.now()).add(const Duration(days: 1)),
  );
  final RxInt adults = 2.obs;
  final RxInt children = 0.obs;

  // Room + add-ons
  // Choose-Room list — fetched from GET /public/room-types (mapped to the
  // booking option). Add-ons are the real bookable extras: spa services, pool
  // cabanas and transfers.
  /// The Choose-Room list, paged through [PaginatedControllerMixin]:
  /// `GET /public/room-types` returns 15 per page. Kept under these names so the
  /// Choose-Room view reads them unchanged.
  RxList<RoomOption> get rooms => items;
  RxBool get roomsLoading => loading;

  /// `room_type_uuid` -> rooms free over the selected dates, from
  /// `GET /public/availability`. A uuid absent from this map means "not asked"
  /// (no dates yet, or the check failed) and is treated as bookable: the
  /// reservation endpoint re-checks availability and returns `no_availability`,
  /// so a failed pre-check must not hide a room that is actually free.
  final RxMap<String, int> roomsAvailable = <String, int>{}.obs;

  /// Rooms free over the selected dates, or null when unknown. Zero is a real
  /// answer — the card shows "Sold out" and refuses selection.
  int? availableFor(RoomOption room) => roomsAvailable[room.uuid];

  /// False only when the check came back with none left.
  bool isBookable(RoomOption room) => (roomsAvailable[room.uuid] ?? 1) > 0;

  /// The Add-Ons step's catalogue, fetched from the three public bookable
  /// endpoints. Rx because it lands after the step can already be on screen.
  final RxList<AddOn> addOns = <AddOn>[].obs;
  final RxBool addOnsLoading = false.obs;
  final Rxn<RoomOption> selectedRoom = Rxn<RoomOption>();
  final RxSet<String> selectedAddOnIds = <String>{}.obs;

  /// True when the booking began from a room tapped on Home, so the room is
  /// already chosen and the Choose-Room step is skipped after picking dates.
  final RxBool roomPreselected = false.obs;

  /// Which photo of [selectedRoom] the Room Details carousel is showing.
  final RxInt roomImageIndex = 0.obs;

  /// True while [openRoomListing] is fetching a tapped room. Exists so the tap
  /// can be de-duplicated without a modal loading dialog.
  final RxBool openingRoom = false.obs;

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
  /// fetched; until it lands the screens show [roomSubtotalEstimate]. The
  /// breakdown/total read from this, not a client tax/promo calc.
  final Rxn<Quote> quote = Rxn<Quote>();
  final RxBool quoteLoading = false.obs;

  /// Inline promo error (e.g. `invalid_promo`), shown under the promo field.
  final RxnString promoError = RxnString();

  /// True while `POST /reservations` is in flight (disables the confirm CTA).
  final RxBool isConfirming = false.obs;

  /// Set on a successful `POST /reservations`; shown on the Confirmed screen.
  final RxnString confirmationCode = RxnString();

  /// The full reservation returned by the last successful booking.
  final Rxn<Reservation> lastReservation = Rxn<Reservation>();

  // ── Cross-screen derived values ─────────────────────────────────────────
  int get nights {
    final start = rangeStart.value;
    final end = rangeEnd.value;
    if (start == null || end == null) return 2;
    final n = end.difference(start).inDays;
    return n < 1 ? 1 : n;
  }

  bool get hasDates => rangeStart.value != null && rangeEnd.value != null;

  /// "Aug 14 → Aug 16" — the date range without the nights suffix.
  String get dateRange {
    final start = rangeStart.value;
    final end = rangeEnd.value;
    if (start == null || end == null) return AppTranslations.selectYourDates;
    final f = DateFormat.MMMd();
    return '${f.format(start)} → ${f.format(end)}';
  }

  /// "Aug 14 → Aug 16 · 2 nights" — used by the Plan footer and Choose Room
  /// context bar (Figma shows the nights there).
  String get dateSummary => hasDates
      ? '$dateRange · ${AppTranslations.nightsCount(nights)}'
      : dateRange;

  String get guestSummary {
    // Through the plural helpers: the old inline suffix rendered "2 Adult s"
    // (a stray space before the s) and could not be translated at all.
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
        '${AppTranslations.perNight(room.pricePerNight.toDouble().formatPrice())}';
  }

  /// The room type's nightly price × nights, shown while `GET /public/quote`
  /// is loading or if it fails.
  int get roomSubtotalEstimate =>
      (selectedRoom.value?.pricePerNight ?? 0) * nights;

  /// "$580" — strips a trailing ".00" so whole amounts read cleanly.
  String money(double v) {
    final whole = v == v.roundToDouble();
    return '\$${whole ? v.toStringAsFixed(0) : v.toStringAsFixed(2)}';
  }

  /// Nights the price is based on — the quote's own count when priced, else the
  /// locally-derived nights.
  int get displayNights => quote.value?.nights ?? nights;

  /// Room-subtotal line: the quote's subtotal when priced, else the estimate.
  String get subtotalDisplay => quote.value != null
      ? money(quote.value!.subtotalUsd)
      : '\$$roomSubtotalEstimate';

  /// True when a promo actually reduced the priced total (drives the discount
  /// row in the breakdown).
  bool get hasDiscount => quote.value?.hasPromo ?? false;

  String get discountDisplay =>
      quote.value != null ? '-${money(quote.value!.discountUsd)}' : '';

  /// Grand total shown on the summary card / confirm CTA: the real quote total
  /// when priced, else the estimate.
  String get totalDisplay => quote.value != null
      ? money(quote.value!.totalUsd)
      : '\$$roomSubtotalEstimate';

  /// One model for the breakdown widget. Only valid once a room is chosen —
  /// every screen that renders it sits after Choose Room in the flow.
  BookingPriceSummary get priceSummary => BookingPriceSummary(
    roomName: selectedRoom.value!.name,
    nights: displayNights,
    subtotal: subtotalDisplay,
    hasDiscount: hasDiscount,
    promoCode: promoCtrl.text,
    discount: discountDisplay,
    total: totalDisplay,
  );

  // ── Step 1 — Plan Your Stay ──────────────────────────────────────────────
  void onRangeSelected(DateTime? start, DateTime? end, DateTime focused) {
    focusedDay = focused;
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

  void onPageChanged(DateTime focused) => focusedDay = focused;

  void setAdults(int v) => adults.value = v;

  void setChildren(int v) => children.value = v;

  /// Tapping a check-in/check-out box clears the range so the calendar is
  /// ready for a fresh pick.
  void restartDateSelection() {
    rangeStart.value = null;
    rangeEnd.value = null;
  }

  /// A reservation needs a guest account, so a signed-out guest is stopped at
  /// the first step of the flow — not after choosing a room, add-ons, details
  /// and a card only to be refused at Confirm.
  bool _requireAccount() {
    if (MiddlewareService.find.isAuthenticated) return true;
    // Asked, not redirected: the guest chooses to leave for sign-in, and
    // Cancel keeps them where they were.
    CustomDialogs.showConfirmationDialog(
      title: AppTranslations.signInToBookTitle,
      message: AppTranslations.signInToBook,
      icon: Icons.person_outline,
      accentColor: AppColors.primary,
      confirmationText: AppTranslations.signInButtonLabel,
      cancellationText: AppTranslations.cancel,
      onPressed: () => Get.toNamed(Routes.signIn),
    );
    return false;
  }

  /// One page of room types, plus the availability of exactly those rooms. The
  /// check runs per page so a room arriving on page 2 is checked when it lands,
  /// rather than only the first page ever being checked.
  @override
  Future<({List<RoomOption> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/room-types',
      queryParameters: {'page': page},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    final pageRooms = res.data!
        .whereType<Map<String, dynamic>>()
        .map(RoomType.fromJson)
        .map(RoomOption.fromRoomType)
        .toList();
    // Not awaited: the cards render now and the Sold Out / scarcity labels
    // fill in when the checks answer.
    _loadAvailability(pageRooms);
    return (items: pageRooms, pagination: res.meta ?? Pagination());
  }

  /// The hotel's standard arrival hour, used as the default slot for extras
  /// booked through the wizard (the guest can reschedule from Services).
  static const int _addOnScheduleHour = 15;

  // ── Step 3 — Add-Ons ─────────────────────────────────────────────────────
  String get addOnsCtaLabel {
    final n = selectedAddOnIds.length;
    return n == 0
        ? AppTranslations.skipNoExtras
        : AppTranslations.continueWithExtras(n);
  }

  /// USD total of the selected extras, for the Add-Ons step's summary pill.
  /// Not folded into the room quote: extras are booked separately through
  /// `POST /service-bookings` and are billed to the folio, not to the
  /// reservation total the backend quoted.
  double get selectedAddOnsTotalUsd => addOns
      .where((addOn) => selectedAddOnIds.contains(addOn.id))
      .fold(0, (sum, addOn) => sum + addOn.price);

  // ── Step 4 — Guest Details ───────────────────────────────────────────────
  final guestFormKey = GlobalKey<FormState>();

  // ── Step 5 — Payment ─────────────────────────────────────────────────────
  /// Whether all four card fields are filled. Recomputed by
  /// [onPaymentFieldChanged] rather than being a getter over the
  /// TextEditingControllers: their `.text` is not observable, so an Obx reading
  /// it would never be notified. This is the one piece of payment state that
  /// has to be pushed rather than pulled.
  final RxBool isCardComplete = false.obs;

  /// The Review Booking CTA is only enabled once payment details are valid.
  bool get canReviewBooking =>
      paymentMethod.value != PaymentMethod.card || isCardComplete.value;

  void selectPaymentMethod(PaymentMethod m) => paymentMethod.value = m;

  void onPaymentFieldChanged() {
    isCardComplete.value =
        cardNumberCtrl.text.trim().isNotEmpty &&
        cardExpiryCtrl.text.trim().isNotEmpty &&
        cardCvvCtrl.text.trim().isNotEmpty &&
        cardNameCtrl.text.trim().isNotEmpty;
  }

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

  String _fmtDate(DateTime d) => DateFormat('yyyy-MM-dd', 'en').format(d);

  /// Fetches the server price for the current room + dates (+ optional promo).
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
    if (res.hasData) {
      quote.value = Quote.fromJson(res.data!);
      promoError.value = null;
      // Room, dates or promo changed the price the rewards were measured on.
      _repriceLoyalty();
    } else if (res.error?.errorCode == ErrorCodes.invalidPromo) {
      // Keep the prior (un-promo) quote so the price doesn't vanish; just flag it.
      promoError.value = AppTranslations.promoInvalid;
      promoApplied.value = false;
    }
  }

  // ── Rewards at booking (points or a voucher) ──────────────────────────────

  final Rxn<LoyaltyAccount> loyaltyAccount = Rxn<LoyaltyAccount>();

  /// `none`, `points` or `voucher` — points and a voucher never combine.
  final RxString loyaltyMode = 'none'.obs;
  final pointsCtrl = TextEditingController();
  final voucherCtrl = TextEditingController();
  final Rxn<LoyaltyPreview> loyaltyPreview = Rxn<LoyaltyPreview>();
  final RxnString loyaltyError = RxnString();
  final RxBool loyaltyPricing = false.obs;
  Timer? _previewTimer;

  /// Bumped by every preview request and every clear; an answer whose number
  /// is no longer current was priced for old inputs and is dropped.
  int _previewSeq = 0;

  /// The `Idempotency-Key` of the booking being attempted, with the body it was
  /// minted for. A lost connection retried with the same inputs reuses it, so
  /// the server returns the booking it already made instead of spending the
  /// points twice; changed inputs get a new key, which is a new intent.
  String? _bookingKey;
  String? _bookingKeyBody;

  /// A voucher code is `LOY-` + 8 characters; the server ignores case and
  /// spaces. Until the guest has typed that much, a preview could only answer
  /// "invalid code" for a code they are still typing, so none is asked for.
  static bool isCompleteVoucherCode(String code) =>
      code.replaceAll(RegExp(r'\s'), '').length >= 12;

  static String _points(dynamic value) =>
      ((value as num?)?.toInt() ?? 0).formatPoints();

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
      ? AppTranslations.confirmBooking
      : AppTranslations.confirmAndPay(totalDisplay);

  void reviewBooking() {
    if (!canReviewBooking) return;
    loadLoyaltyAccount();
    Get.toNamed(Routes.reviewBooking);
  }

  /// True when the reservation just made still waits on the hotel — the
  /// Confirmation screen then says so instead of claiming the stay is
  /// confirmed. False if the server ever answers with it already confirmed.
  bool get awaitingConfirmation =>
      lastReservation.value?.isAwaitingHotel ?? false;

  /// Clears every field back to its starting value — call before entering the
  /// flow for a new booking (not after finishing one), so "Book Again" never
  /// shows a previous attempt's guest/card details.
  void reset() {
    final today = DateUtils.dateOnly(DateTime.now());
    focusedDay = today;
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
    isCardComplete.value = false;
    promoCtrl.clear();
    promoApplied.value = false;
    promoError.value = null;
    _resetLoyalty();
    quote.value = null;
    quoteLoading.value = false;
    isConfirming.value = false;
    confirmationCode.value = null;
    lastReservation.value = null;
  }

  @override
  void onInit() {
    super.onInit();
    initPagination(_cancelToken);
  }

  @override
  void onClose() {
    _cancelToken.cancel();
    _previewTimer?.cancel();
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
      pointsCtrl,
      voucherCtrl,
    ]) {
      c.dispose();
    }
    phone.dispose();
    super.onClose();
  }
}
