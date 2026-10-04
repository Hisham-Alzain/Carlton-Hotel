// import 'package:carlton/components/reviews/review_submit_sheet.dart';
import 'package:carlton/constants/error_codes.dart';
// import 'package:carlton/controllers/reviews/review_controller.dart';
// import 'package:carlton/customWidgets/custom_bottom_sheet.dart'; // needed by openReviewSheet
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/mixins/paginated_controller_mixin.dart';
import 'package:carlton/models/menu.dart';
import 'package:carlton/models/pagination.dart';
import 'package:carlton/models/service_booking.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';

/// Drives one restaurant detail screen: the menu/info/reserve tabs, the menu
/// category filter, the gallery, and the reservation form. The restaurant
/// arrives via `Get.arguments`; a missing argument pops the screen rather
/// than substituting a stand-in venue.
/// The Menu/Info/Reserve/Reviews TabController deliberately does **not** live
/// here — [RestaurantDetailView] owns it via `DefaultTabController`, so it is
/// tied to the widget's lifetime rather than this controller's.
class RestaurantController extends GetxController
    with PaginatedControllerMixin<MenuItem> {
  final CancelToken _cancelToken = CancelToken();

  late final RestaurantItem restaurant;

  final RxInt categoryIndex = 0.obs;
  final RxInt galleryIndex = 0.obs;

  // ── Venue detail (public: /public/dining-venues/{uuid}) ────────────────────
  /// About/description + gallery, fetched from the venue detail endpoint.
  /// Rating/hours/location/cuisine already ride on [restaurant].
  final RxString about = ''.obs;
  final RxList<String> gallery = <String>[].obs;

  // ── Menu (public content: /public/dining-venues/{uuid}/menu[-categories]) ──
  final RxList<MenuCategory> menuCategories = <MenuCategory>[].obs;

  /// The dishes, paged through [PaginatedControllerMixin]: the menu endpoint
  /// returns 15 per page, so a single fetch cut every menu off at 15 dishes.
  RxList<MenuItem> get menuItems => items;
  RxBool get menuLoading => loading;

  /// Dishes under the selected category chip. Filtered **server-side**
  /// (`?type=slug`), not here — with paging, a client-side filter would hide
  /// every matching dish that sits on a page not yet loaded.
  List<MenuItem> get visibleMenuItems => items;

  /// The selected chip's slug, or null for the full menu (no categories yet).
  String? get _selectedType => categoryIndex.value < menuCategories.length
      ? menuCategories[categoryIndex.value].slug
      : null;

  /// Defaults to today; the guest picks a real date from [pickDate].
  final Rx<DateTime> reserveDate = DateTime.now().obs;

  /// Bookable slots for *this* venue, derived from its published opening hours
  /// (`DiningVenueResource.hours`, e.g. `"7:00 AM – 11:00 PM"`) at half-hour
  /// steps. Venue-specific by construction: a rooftop lounge that opens at 5 PM
  /// never offers a noon slot.
  ///
  /// Empty when the hours string does not parse — the Reserve tab then shows
  /// no chips and [confirmReservation] refuses rather than posting a blank time.
  /// Computed once: [restaurant] is final, so the slots cannot change.
  late final List<String> timeSlots = _slotsFrom(restaurant.hours);

  /// Empty until a slot is chosen (or when the venue published no parseable
  /// hours). [confirmReservation] guards on it.
  final RxString timeSlot = ''.obs;
  final RxInt guests = 2.obs;
  final TextEditingController specialRequests = TextEditingController();

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    // No stand-in venue: rendering someone else's restaurant for a frame is
    // worse than an empty one, so a bad argument leaves this blank and pops.
    restaurant = arg is RestaurantItem ? arg : RestaurantItem.blank;
    if (restaurant.uuid.isEmpty) {
      CustomSnackbars.showError(message: AppTranslations.tableVenueUnavailable);
      // After this frame: onInit runs mid-navigation, and popping from inside it
      // races the route that is still being pushed.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (Get.currentRoute == Routes.restaurantDetail) Get.back<void>();
      });
      return;
    }
    // Pre-select the venue's first bookable slot so the guest is one tap from a
    // valid reservation, matching the design's pre-filled Reserve tab.
    if (timeSlots.isNotEmpty) timeSlot.value = timeSlots.first;
    initPagination(_cancelToken);
    _loadMenu();
  }

  /// Fetches the category chips + dishes for this venue.
  Future<void> _loadMenu() async {
    final base = '/public/dining-venues/${restaurant.uuid}';
    // Venue + categories in parallel; the dishes wait for the categories,
    // because the first page is filtered by the first category's slug.
    final venueF = ApiService.find.get<Map<String, dynamic>>(
      path: base,
      showErrorDialog: false,
    );
    final catF = ApiService.find.get<List<dynamic>>(
      path: '$base/menu-categories',
      showErrorDialog: false,
    );
    final venueRes = await venueF;
    final catRes = await catF;
    if (isClosed) return;
    if (venueRes.hasData) {
      final venue = DiningVenue.fromJson(venueRes.data!);
      about.value = venue.description.value;
      gallery.value = venue.images.map((i) => i.url).toList();
    }
    if (catRes.hasData) {
      menuCategories.value = catRes.data!
          .whereType<Map<String, dynamic>>()
          .map(MenuCategory.fromJson)
          .toList();
    }
    await loadItems(_cancelToken);
  }

  @override
  Future<({List<MenuItem> items, Pagination pagination})?> fetchPage(
    int page,
    CancelToken cancelToken,
  ) async {
    final type = _selectedType;
    final res = await ApiService.find.get<List<dynamic>>(
      path: '/public/dining-venues/${restaurant.uuid}/menu',
      queryParameters: {'page': page, 'type': ?type},
      showErrorDialog: false,
      cancelToken: cancelToken,
    );
    if (!res.hasData) return null;
    return (
      items: res.data!
          .whereType<Map<String, dynamic>>()
          .map(MenuItem.fromJson)
          .toList(),
      pagination: res.meta ?? Pagination(),
    );
  }

  @override
  void onClose() {
    _cancelToken.cancel();
    specialRequests.dispose();
    // Chains into PaginatedControllerMixin.onClose → disposes scrollController.
    super.onClose();
  }

  /// Switching chips refetches from page 1 for that category (server-side
  /// filter); the mixin swaps the list atomically, so there is no empty frame.
  void selectCategory(int index) {
    if (categoryIndex.value == index) return;
    categoryIndex.value = index;
    loadItems(_cancelToken);
  }

  void setGalleryIndex(int index) {
    galleryIndex.value = index;
  }

  void selectTimeSlot(String slot) {
    timeSlot.value = slot;
  }

  void setGuests(int value) {
    guests.value = value;
  }

  /// The picker's branding (cream surface, primary header, gold today ring)
  /// comes from `Themes.theme`'s datePickerTheme — nothing to override here.
  Future<void> pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: Get.context!,
      // Clamp: the stored date can fall behind `now` if the screen is left open
      // past midnight, which would trip the initialDate assertion.
      initialDate: reserveDate.value.isBefore(now) ? now : reserveDate.value,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked != null) {
      reserveDate.value = picked;
    }
  }

  void downloadMenu() =>
      CustomSnackbars.showInfo(message: AppTranslations.menuDownloadComingSoon);

  // Reviews switched off for now (see RestaurantDetailView).
  // /// Opens the "Write a Review" sheet for this venue. Lives here rather than in
  // /// the Reviews tab because it both auth-gates and navigates: a POST while
  // /// unauthenticated returns 401 and fires the global logout — the wrong
  // /// outcome for a review attempt.
  // void openReviewSheet() {
  // if (!MiddlewareService.find.isAuthenticated) {
  // CustomSnackbars.showInfo(message: AppTranslations.signInToReview);
  // Get.toNamed(Routes.signIn);
  // return;
  // }
  // final reviews = Get.find<ReviewController>();
  // CustomBottomSheet.show<bool>(
  // title: AppTranslations.writeAReview,
  // subtitle: restaurant.name,
  // child: ReviewSubmitSheet(
  // onSubmit: ({required rating, required comment}) =>
  // reviews.submitReview(rating: rating, comment: comment),
  // ),
  // );
  // }

  /// Reserves a table (`POST /dining-venues/{uuid}/table-reservations`,
  /// tier-3a). A demo venue (no uuid) just confirms locally; a guest with no
  /// booking is prompted to book first. The backend picks the table — we send
  /// only date/time/party size.
  Future<void> confirmReservation() async {
    // `time` is required and validated as `H:i` server-side, so an unset slot
    // would come back as a 422 the guest cannot act on. Say what is missing.
    if (timeSlot.value.isEmpty) {
      CustomSnackbars.showInfo(message: AppTranslations.tablePickTime);
      return;
    }
    if (!MiddlewareService.find.hasBooking) {
      CustomSnackbars.showInfo(message: AppTranslations.tableNeedsBooking);
      return;
    }
    final res = await ApiService.find.post<Map<String, dynamic>>(
      path: '/dining-venues/${restaurant.uuid}/table-reservations',
      data: {
        'date': reserveDate.value.formatApiDate(),
        'time': _toTime24h(timeSlot.value),
        'guest_count': guests.value,
        if (specialRequests.text.trim().isNotEmpty)
          'special_request': specialRequests.text.trim(),
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 201 && res.data != null) {
      final booking = ServiceBooking.fromJson(res.data!);
      final label = booking.label.isNotEmpty
          ? booking.label
          : '${guests.value}';
      CustomSnackbars.showSuccess(
        message: AppTranslations.tableReservedFor(label),
      );
      return;
    }
    switch (res.error?.errorCode) {
      case ErrorCodes.noAvailability:
        CustomSnackbars.showError(message: AppTranslations.tableNoAvailability);
      case ErrorCodes.noActiveReservation:
        CustomSnackbars.showError(message: AppTranslations.tableNeedsBooking);
      case ErrorCodes.notFound:
        CustomSnackbars.showError(
          message: AppTranslations.tableVenueUnavailable,
        );
      case ErrorCodes.validationFailed:
        CustomSnackbars.showError(
          message: res.error?.message ?? AppTranslations.tableFailed,
        );
      default:
        CustomSnackbars.showError(message: AppTranslations.tableFailed);
    }
  }

  /// Converts a 12-hour slot label ("7:00 PM", "12:30 PM") to the `H:i` (24h)
  /// the reservation endpoint expects.
  String _toTime24h(String slot) {
    final trimmed = slot.trim().toUpperCase();
    final isPm = trimmed.endsWith('PM');
    final isAm = trimmed.endsWith('AM');
    final body = trimmed.replaceAll(RegExp(r'\s*[AP]M$'), '').trim();
    final parts = body.split(':');
    var hour = int.tryParse(parts[0]) ?? 0;
    final minute = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    if (isPm && hour != 12) hour += 12;
    if (isAm && hour == 12) hour = 0;
    return '${hour.toString().padLeft(2, '0')}:'
        '${minute.toString().padLeft(2, '0')}';
  }

  /// Half-hour slot labels between the two ends of an opening-hours string.
  ///
  /// Accepts the shape the CMS publishes — `"7:00 AM – 11:00 PM"`, with
  /// either dash character — and returns `const []` for anything else
  /// ("24 hours", a localized phrase, an empty field). Returning empty rather
  /// than guessing is deliberate: an invented slot the kitchen does not serve
  /// produces a reservation the hotel has to call the guest to cancel.
  ///
  /// A closing time before the opening time means the venue runs past midnight
  /// (the rooftop lounge closes at 1:00 AM), so the end is pushed a day on.
  static List<String> _slotsFrom(String hours) {
    final parts = hours.split(RegExp('[–—-]'));
    if (parts.length != 2) return const [];
    final open = _parseClock(parts[0]);
    final close = _parseClock(parts[1]);
    if (open == null || close == null) return const [];
    var endMinutes = close;
    if (endMinutes <= open) endMinutes += _minutesPerDay;

    final slots = <String>[];
    // The last seating is a slot before closing, not at it.
    for (
      var m = open;
      m <= endMinutes - _slotStepMinutes;
      m += _slotStepMinutes
    ) {
      slots.add(_clockLabel(m % _minutesPerDay));
    }
    return slots;
  }

  static const int _slotStepMinutes = 30;
  static const int _minutesPerDay = 24 * 60;

  /// `"7:00 AM"` -> minutes past midnight, or null when it is not a clock time.
  static int? _parseClock(String raw) {
    final match = RegExp(
      r'^\s*(\d{1,2})(?::(\d{2}))?\s*([AaPp])\.?[Mm]\.?\s*$',
    ).firstMatch(raw);
    if (match == null) return null;
    var hour = int.parse(match.group(1)!);
    final minute = int.tryParse(match.group(2) ?? '0') ?? 0;
    if (hour < 1 || hour > 12 || minute > 59) return null;
    final isPm = match.group(3)!.toUpperCase() == 'P';
    if (isPm && hour != 12) hour += 12;
    if (!isPm && hour == 12) hour = 0;
    return hour * 60 + minute;
  }

  /// Inverse of [_parseClock], in the same 12-hour shape [_toTime24h] reads
  /// back — the label is both what the chip shows and what gets converted for
  /// the wire, so the two must round-trip.
  static String _clockLabel(int minutes) {
    final hour24 = minutes ~/ 60;
    final minute = minutes % 60;
    final suffix = hour24 < 12 ? 'AM' : 'PM';
    final hour12 = hour24 % 12 == 0 ? 12 : hour24 % 12;
    return '$hour12:${minute.toString().padLeft(2, '0')} $suffix';
  }
}
