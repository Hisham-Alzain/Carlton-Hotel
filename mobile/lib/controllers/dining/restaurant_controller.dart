import 'package:carlton/constants/error_codes.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/extensions/date_extension.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/dining_venue.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/models/menu.dart';
import 'package:carlton/models/service_booking.dart';
import 'package:carlton/services/api/api_service.dart';
import 'package:carlton/services/middleware_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Drives one restaurant detail screen: the menu/info/reserve tabs, the menu
/// category filter, the gallery, and the reservation form. The restaurant
/// arrives via `Get.arguments`; a missing or wrong argument pops the route.
/// The Menu/Info/Reserve/Reviews TabController deliberately does **not** live
/// here — [RestaurantDetailView] owns it via `DefaultTabController`, so it is
/// tied to the widget's lifetime rather than this controller's.
class RestaurantController extends GetxController {
  /// The venue this screen renders.
  ///
  /// Deliberately not `late final`: [RestaurantDetailView] builds in the same
  /// frame [onInit] runs, so on a bad `Get.arguments` the view read this field
  /// before the scheduled `Get.back()` could fire and threw
  /// `LateInitializationError` — a red screen instead of a graceful pop. It
  /// holds [RestaurantItem.blank] for that one frame instead.
  RestaurantItem restaurant = RestaurantItem.blank;

  final RxInt categoryIndex = 0.obs;
  final RxInt galleryIndex = 0.obs;

  // ── Venue detail (public: /public/dining-venues/{uuid}) ────────────────────
  /// About/description + gallery, fetched from the venue detail endpoint.
  /// Rating/hours/location/cuisine already ride on [restaurant].
  final RxString about = ''.obs;
  final RxList<String> gallery = <String>[].obs;

  // ── Menu (public content: /public/dining-venues/{uuid}/menu[-categories]) ──
  final RxList<MenuCategory> menuCategories = <MenuCategory>[].obs;
  final RxList<MenuItem> menuItems = <MenuItem>[].obs;
  final RxBool menuLoading = true.obs;

  /// Dishes under the selected category chip (filtered by slug). With no
  /// categories yet, every fetched dish shows.
  List<MenuItem> get visibleMenuItems {
    if (menuCategories.isEmpty ||
        categoryIndex.value >= menuCategories.length) {
      return menuItems;
    }
    final slug = menuCategories[categoryIndex.value].slug;
    return menuItems.where((m) => m.type == slug).toList();
  }

  final Rx<DateTime> reserveDate = Rx(
    DateUtils.dateOnly(DateTime.now()).add(const Duration(days: 1)),
  );
  // No backend slots endpoint exists yet — starts unselected until one is
  // wired; see AppTranslations.selectYourDatesFirst-style empty state.
  final RxString timeSlot = ''.obs;
  final RxInt guests = 2.obs;
  final TextEditingController specialRequests = TextEditingController();

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    // Both entry points (Home rail, Discover list) always pass a
    // RestaurantItem, so a missing or wrong argument is a routing bug. Bail
    // out rather than render a placeholder venue the guest can't act on.
    if (arg is! RestaurantItem) {
      WidgetsBinding.instance.addPostFrameCallback((_) => Get.back());
      return;
    }
    restaurant = arg;
    _loadMenu();
  }

  /// Fetches the category chips + dishes for this venue. A demo restaurant
  /// (no uuid) simply renders the empty state rather than calling the API.
  Future<void> _loadMenu() async {
    if (restaurant.uuid.isEmpty) {
      menuLoading.value = false;
      return;
    }
    final base = '/public/dining-venues/${restaurant.uuid}';
    // Fire all three in parallel (mixed response shapes, so awaited separately).
    final venueF = ApiService.find.get<Map<String, dynamic>>(
      path: base,
      showErrorDialog: false,
    );
    final catF = ApiService.find.get<List<dynamic>>(
      path: '$base/menu-categories',
      showErrorDialog: false,
    );
    final menuF = ApiService.find.get<List<dynamic>>(
      path: '$base/menu',
      showErrorDialog: false,
    );
    final venueRes = await venueF;
    final catRes = await catF;
    final menuRes = await menuF;
    if (isClosed) return;
    if (venueRes.statusCode == 200 && venueRes.data != null) {
      final venue = DiningVenue.fromJson(venueRes.data!);
      about.value = venue.description.value;
      gallery.assignAll(venue.images.map((i) => i.url));
    }
    if (catRes.statusCode == 200 && catRes.data != null) {
      menuCategories.assignAll(
        catRes.data!.whereType<Map<String, dynamic>>().map(
          MenuCategory.fromJson,
        ),
      );
    }
    if (menuRes.statusCode == 200 && menuRes.data != null) {
      menuItems.assignAll(
        menuRes.data!.whereType<Map<String, dynamic>>().map(MenuItem.fromJson),
      );
    }
    menuLoading.value = false;
  }

  @override
  void onClose() {
    specialRequests.dispose();
    super.onClose();
  }

  void selectCategory(int index) {
    categoryIndex.value = index;
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
      // Clamp so a demo date in the past never trips the initialDate assertion.
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

  /// Reserves a table (`POST /dining-venues/{uuid}/table-reservations`,
  /// tier-3a). A demo venue (no uuid) just confirms locally; a guest with no
  /// booking is prompted to book first. The backend picks the table — we send
  /// only date/time/party size.
  Future<void> confirmReservation() async {
    if (restaurant.uuid.isEmpty) {
      CustomSnackbars.showSuccess(
        message: AppTranslations.tableReservedFor(
          AppTranslations.reservationSummary('${guests.value}', timeSlot.value),
        ),
      );
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

  /// Converts a demo 12-hour slot ("7:00 PM", "12:30 PM") to the `H:i` (24h)
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
}
