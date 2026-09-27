import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/extensions/price_extension.dart';
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
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

/// Shared controller for the whole 5-step booking flow (Plan → Choose Room →
/// Add-Ons → Guest → Payment) plus the Room Details sheet. Registered once,
/// permanently, at app boot (see main.dart) instead of through a per-route
/// binding — every booking screen reads/writes and rebuilds off this same
/// instance, observing its Rx state through Obx. Stays a GetxController (not
/// GetxService) purely for the lifecycle hooks; the permanent, no-binding
/// registration is what makes it behave like a service. Being permanent (not
/// tied to a route's fenix
/// lifecycle) also means [reset] is the only thing that clears it — callers
/// starting a new booking must call it explicitly (see
/// BookView/StaysController.startBooking), otherwise a completed booking's
/// guest/card details would carry into the next one.
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
  /// fetched, or for a pure-demo room (no uuid) where we fall back to an
  /// estimate. The breakdown/total read from this, not a client tax/promo calc.
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
    final f = DateFormat('MMM d');
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

  /// Fallback subtotal (room nightly × nights) used only for a pure-demo room
  /// with no uuid, where the quote endpoint can't be called.
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

  Future<void> searchRooms() async {
    if (!hasDates) {
      CustomSnackbars.showInfo(message: AppTranslations.selectYourDatesFirst);
      return;
    }
    // Room already chosen on Home → skip Choose-Room, go straight to add-ons,
    // after checking that room is free on the picked dates (the Choose-Room
    // list does this for every room; this path would otherwise skip it).
    final preselected = selectedRoom.value;
    if (roomPreselected.value && preselected != null) {
      roomsAvailable.remove(preselected.uuid);
      await _loadAvailability([preselected]);
      if (isClosed) return;
      if (!isBookable(preselected)) {
        CustomSnackbars.showInfo(message: AppTranslations.roomSoldOut);
        return;
      }
      Get.toNamed(Routes.addOns);
      return;
    }
    Get.toNamed(Routes.chooseRoom);
    loadRooms();
  }

  /// Loads the Choose-Room list from `GET /public/room-types`, mapped to the
  /// booking option, then checks each one against the selected dates. The
  /// Choose-Room list repaints when the rooms land and again when availability
  /// does, so the cards appear immediately rather than waiting on N checks.
  Future<void> loadRooms() async {
    // New dates mean every earlier count is stale.
    roomsAvailable.clear();
    await loadItems(_cancelToken);
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
    if (res.statusCode != 200 || res.data == null) return null;
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

  /// Fills [roomsAvailable] from `GET /public/availability`, one request per room
  /// type — the endpoint takes a single `room_type_uuid` and there is no bulk
  /// form, so they are fired together rather than in sequence.
  ///
  /// Silent and best-effort: this is a courtesy pre-check so the guest is not
  /// offered a room that is gone. `POST /reservations` re-checks server-side and
  /// returns `no_availability`, which is the authoritative answer and already
  /// has an error branch, so a failure here degrades to "unknown" rather than
  /// blocking the step.
  Future<void> _loadAvailability(List<RoomOption> pageRooms) async {
    if (!hasDates) return;
    final checkIn = _fmtDate(rangeStart.value!);
    final checkOut = _fmtDate(rangeEnd.value!);
    final bookable = pageRooms.where((room) => room.uuid.isNotEmpty).toList();
    if (bookable.isEmpty) return;

    final responses = await Future.wait(
      bookable.map(
        (room) => ApiService.find.get<Map<String, dynamic>>(
          path: '/public/availability',
          queryParameters: {
            'room_type_uuid': room.uuid,
            'check_in': checkIn,
            'check_out': checkOut,
          },
          showErrorDialog: false,
        ),
      ),
    );
    if (isClosed) return;
    final counts = <String, int>{};
    for (final (index, res) in responses.indexed) {
      if (res.statusCode != 200 || res.data == null) continue;
      final count = (res.data!['rooms_available'] as num?)?.toInt();
      // `available` is the boolean form of the same answer; prefer the count so
      // the card can say how many are left.
      if (count != null) {
        counts[bookable[index].uuid] = count;
      } else if (res.data!['available'] == false) {
        counts[bookable[index].uuid] = 0;
      }
    }
    // Merged, not replaced: each page adds its own rooms' counts.
    roomsAvailable.addAll(counts);
  }

  // ── Step 2 — Choose Your Room + Room Details sheet ──────────────────────
  void setRoomImage(int index) => roomImageIndex.value = index;

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
    if (uuid.isEmpty || openingRoom.value) return;
    // No `showLoading: true`: tapping a room card must not throw a modal
    // loading dialog over Home. The re-entrancy guard replaces what the modal
    // was incidentally providing — blocking a second tap mid-fetch, which
    // would otherwise push the details route twice.
    openingRoom.value = true;
    final res = await ApiService.find.get<Map<String, dynamic>>(
      path: '/public/room-types/$uuid',
      showErrorDialog: false,
    );
    if (isClosed) return;
    openingRoom.value = false;
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
    // "Plan Your Stay" is the Book tab in the Main shell (no standalone route),
    // so pop back to the shell and switch to it (index 2). The switch itself
    // resets the draft, so the room is set only after it — setting it first
    // wiped it again and sent the guest to Choose-Room for a room already
    // chosen.
    Get.until((r) => r.isFirst);
    reset();
    Get.find<MainController>().changeTab(2);
    selectedRoom.value = room;
    roomPreselected.value = true;
  }

  void selectRoom(RoomOption room) {
    // Refused here rather than at `POST /reservations` three steps later, where
    // the guest would have entered their details and card first.
    if (!isBookable(room)) {
      CustomSnackbars.showInfo(message: AppTranslations.roomSoldOut);
      return;
    }
    selectedRoom.value = room;
    Get.toNamed(Routes.addOns);
  }

  /// Loads the Add-Ons step's catalogue from the three public bookable lists.
  ///
  /// Each is a separate endpoint with its own resource shape, so they are mapped
  /// into one [AddOn] list here rather than at three call sites. A list that
  /// fails is simply absent — one unreachable endpoint must not blank the
  /// other two, and the step is skippable by design.
  Future<void> loadAddOns() async {
    if (addOns.isNotEmpty || addOnsLoading.value) return;
    addOnsLoading.value = true;
    final results = await Future.wait([
      ApiService.find.get<List<dynamic>>(
        path: '/public/spa-services',
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/pool-cabanas',
        showErrorDialog: false,
      ),
      ApiService.find.get<List<dynamic>>(
        path: '/public/transfers',
        showErrorDialog: false,
      ),
    ]);
    if (isClosed) return;
    final next = <AddOn>[
      ..._mapBookables(
        results[0],
        type: 'spa_service',
        iconPath: 'assets/icons/jacuzzi.svg',
        subtitle: (json) {
          final minutes = (json['duration_minutes'] as num?)?.toInt();
          return minutes == null ? '' : AppTranslations.etaMinutes(minutes);
        },
      ),
      ..._mapBookables(
        results[1],
        type: 'pool_cabana',
        iconPath: 'assets/icons/view.svg',
        subtitle: (json) {
          final capacity = (json['capacity'] as num?)?.toInt();
          return capacity == null ? '' : '$capacity ${AppTranslations.guests}';
        },
      ),
      ..._mapBookables(
        results[2],
        type: 'transfer',
        iconPath: 'assets/icons/location.svg',
        subtitle: (json) => '',
      ),
    ];
    addOns.assignAll(next);
    addOnsLoading.value = false;
  }

  /// Shared mapping for the three bookable lists, which differ only in their
  /// secondary field (duration / capacity / nothing).
  List<AddOn> _mapBookables(
    ApiResponse<List<dynamic>> response, {
    required String type,
    required String iconPath,
    required String Function(Map<String, dynamic> json) subtitle,
  }) {
    if (response.statusCode != 200 || response.data == null) return const [];
    return response.data!
        .whereType<Map<String, dynamic>>()
        .where((json) => json['is_active'] as bool? ?? true)
        .map(
          (json) => AddOn(
            id: json['uuid'] as String? ?? '',
            bookableType: type,
            iconPath: iconPath,
            title: Localized.fromJson(json['name']).value,
            subtitle: subtitle(json),
            priceUsd: json['price_usd']?.toString() ?? '0',
          ),
        )
        .where((addOn) => addOn.id.isNotEmpty)
        .toList();
  }

  /// Books every selected extra against the reservation that was just created.
  ///
  /// Deliberately runs *after* `POST /reservations` rather than on the Add-Ons
  /// step: `POST /service-bookings` sits behind the `has_booking` gate, so a
  /// guest with no reservation yet would get a 403 for every selection. Each
  /// booking is scheduled for the arrival date.
  ///
  /// Best-effort and silent: the room is already confirmed by this point, and
  /// failing the whole booking over an unavailable cabana would be worse than
  /// the guest re-requesting it from the Services tab. Returns the extras that
  /// did not go through so the caller can say so.
  Future<List<AddOn>> _bookSelectedAddOns() async {
    final selected = addOns
        .where((addOn) => selectedAddOnIds.contains(addOn.id))
        .toList();
    if (selected.isEmpty) return const [];
    final arrival = rangeStart.value;
    if (arrival == null) return selected;
    // The endpoint requires `after:now`; a same-day booking made this afternoon
    // would fail against midnight, so schedule for the hotel's check-in hour
    // and push to the next slot if that moment has already passed.
    var scheduled = DateTime(
      arrival.year,
      arrival.month,
      arrival.day,
      _addOnScheduleHour,
    );
    final now = DateTime.now();
    if (!scheduled.isAfter(now)) scheduled = now.add(const Duration(hours: 1));

    final failed = <AddOn>[];
    for (final addOn in selected) {
      final res = await ApiService.find.post<Map<String, dynamic>>(
        path: '/service-bookings',
        data: {
          'bookable_type': addOn.bookableType,
          'bookable_uuid': addOn.id,
          'scheduled_at': scheduled.toIso8601String(),
        },
        showErrorDialog: false,
      );
      if (!res.ok) failed.add(addOn);
      if (isClosed) return failed;
    }
    return failed;
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

  void toggleAddOn(String id) {
    // RxSet.remove/add notify on their own.
    if (!selectedAddOnIds.remove(id)) selectedAddOnIds.add(id);
  }

  /// USD total of the selected extras, for the Add-Ons step's summary pill.
  /// Not folded into the room quote: extras are booked separately through
  /// `POST /service-bookings` and are billed to the folio, not to the
  /// reservation total the backend quoted.
  double get selectedAddOnsTotalUsd => addOns
      .where((addOn) => selectedAddOnIds.contains(addOn.id))
      .fold(0, (sum, addOn) => sum + addOn.price);

  void continueFromAddOns() => Get.toNamed(Routes.guestDetails);

  // ── Step 4 — Guest Details ───────────────────────────────────────────────
  final guestFormKey = GlobalKey<FormState>();

  void continueFromGuest() {
    // if (!guestFormKey.currentState!.validate()) return;
    Get.toNamed(Routes.payment);
    // Price the stay for the Payment summary + Review breakdown.
    // Fire-and-forget: the summary repaints when the quote lands.
    _fetchQuote(promo: promoApplied.value ? promoCtrl.text.trim() : null);
  }

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
      promoError.value = AppTranslations.promoInvalid;
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
      ? AppTranslations.confirmBooking
      : AppTranslations.confirmAndPay(totalDisplay);

  void reviewBooking() {
    if (!canReviewBooking) return;
    Get.toNamed(Routes.reviewBooking);
  }

  /// Confirms via `POST /reservations` (tier-2, token identity). Card/wallet
  /// methods have no gateway yet, so they're blocked here with a clear message
  /// rather than submitting. On success the guest gains a booking and the real
  /// `booking_code` shows on the Confirmed screen.
  /// True when the reservation just made still waits on the hotel
  /// (`pending` / `pending_verification`) — the Confirmation screen then says
  /// so instead of claiming the stay is confirmed.
  bool get awaitingConfirmation {
    final status = lastReservation.value?.status;
    return status == 'pending' || status == 'pending_verification';
  }

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
      CustomSnackbars.showInfo(message: AppTranslations.cardWalletUnavailable);
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
      // A guest's own booking is created `pending`: the server counts it toward
      // `has_booking` (which unlocks the booked Home and `POST
      // /service-bookings`) only once the hotel confirms it. So the local
      // entitlement flips only for a reservation that is already confirmed.
      if (!awaitingConfirmation) {
        final guest = MiddlewareService.find.guest.value;
        if (guest != null) {
          MiddlewareService.find.updateGuest(guest.copyWith(hasBooking: true));
        }
        // Extras are booked here, not on the Add-Ons step: `POST
        // /service-bookings` is gated on `has_booking`.
        final failed = await _bookSelectedAddOns();
        if (isClosed) return;
        if (failed.isNotEmpty) {
          CustomSnackbars.showWarning(
            message: AppTranslations.addOnsNotBooked(
              failed.map((addOn) => addOn.title).join(', '),
            ),
          );
        }
      } else {
        // Home switches to the pre-arrival layout straight away; it reloads
        // the stay itself when the guest lands there.
        MiddlewareService.find.hasPendingBooking.value = true;
        // Posting now would only collect a 403 per extra; say when they can be
        // booked instead.
        final chosen = addOns
            .where((addOn) => selectedAddOnIds.contains(addOn.id))
            .map((addOn) => addOn.title)
            .toList();
        if (chosen.isNotEmpty) {
          CustomSnackbars.showInfo(
            message: AppTranslations.addOnsAfterConfirmation(chosen.join(', ')),
          );
        }
      }
      // The Stays list and Home were loaded before this booking existed.
      if (Get.isRegistered<StaysController>()) {
        Get.find<StaysController>().reloadUpcoming();
      }
      if (Get.isRegistered<HomeController>()) {
        Get.find<HomeController>().reloadBooking();
      }
      Get.toNamed(Routes.bookingConfirmed);
      return;
    }

    final message = switch (res.error?.errorCode) {
      ErrorCodes.noAvailability => AppTranslations.datesSoldOut,
      ErrorCodes.invalidPromo => AppTranslations.promoInvalid,
      _ => res.error?.message ?? AppTranslations.bookingFailed,
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
    // A new booking is never "active" (that is a checked-in stay) — open the
    // Upcoming tab, where it is listed.
    if (Get.isRegistered<StaysController>()) {
      Get.find<StaysController>().tabController.animateTo(1);
    }
  }

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
