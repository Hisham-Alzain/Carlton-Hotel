import 'package:carlton/constants/demo_data.dart';
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
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Drives one restaurant detail screen: the menu/info/reserve tabs, the menu
/// category filter, the gallery, and the reservation form. The restaurant
/// arrives via `Get.arguments`; falls back to the first demo restaurant.
class RestaurantController extends GetxController
    with GetSingleTickerProviderStateMixin {
  late final RestaurantItem restaurant;

  /// Drives the Material TabBar (Menu / Info / Reserve / Reviews).
  late final TabController tabController;
  int categoryIndex = 0;
  int galleryIndex = 0;

  // ── Venue detail (public: /public/dining-venues/{uuid}) ────────────────────
  /// About/description + gallery, fetched from the venue detail endpoint.
  /// Rating/hours/location/cuisine already ride on [restaurant].
  String about = '';
  List<String> gallery = [];

  // ── Menu (public content: /public/dining-venues/{uuid}/menu[-categories]) ──
  List<MenuCategory> menuCategories = [];
  List<MenuItem> menuItems = [];
  bool menuLoading = true;

  /// Dishes under the selected category chip (filtered by slug). With no
  /// categories yet, every fetched dish shows.
  List<MenuItem> get visibleMenuItems {
    if (menuCategories.isEmpty || categoryIndex >= menuCategories.length) {
      return menuItems;
    }
    final slug = menuCategories[categoryIndex].slug;
    return menuItems.where((m) => m.type == slug).toList();
  }

  DateTime reserveDate = DateTime(2026, 8, 14);
  String timeSlot = DemoData.reserveTimeSlots[2];
  int guests = 2;
  final TextEditingController specialRequests = TextEditingController();

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    restaurant = arg is RestaurantItem ? arg : DemoData.restaurants.first;
    tabController = TabController(length: 4, vsync: this);
    _loadMenu();
  }

  /// Fetches the category chips + dishes for this venue. A demo restaurant
  /// (no uuid) simply renders the empty state rather than calling the API.
  Future<void> _loadMenu() async {
    if (restaurant.uuid.isEmpty) {
      menuLoading = false;
      update();
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
      about = venue.description.value;
      gallery = venue.images.map((i) => i.url).toList();
    }
    if (catRes.statusCode == 200 && catRes.data != null) {
      menuCategories = catRes.data!
          .whereType<Map<String, dynamic>>()
          .map(MenuCategory.fromJson)
          .toList();
    }
    if (menuRes.statusCode == 200 && menuRes.data != null) {
      menuItems = menuRes.data!
          .whereType<Map<String, dynamic>>()
          .map(MenuItem.fromJson)
          .toList();
    }
    menuLoading = false;
    update();
  }

  @override
  void onClose() {
    tabController.dispose();
    specialRequests.dispose();
    super.onClose();
  }

  void selectCategory(int index) {
    categoryIndex = index;
    update();
  }

  void setGalleryIndex(int index) {
    galleryIndex = index;
    update();
  }

  void selectTimeSlot(String slot) {
    timeSlot = slot;
    update();
  }

  void setGuests(int value) {
    guests = value;
    update();
  }

  Future<void> pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: Get.context!,
      // Clamp so a demo date in the past never trips the initialDate assertion.
      initialDate: reserveDate.isBefore(now) ? now : reserveDate,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
      // Brand the picker: primary header/selection on a clean cream surface.
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: AppColors.primary,
            onPrimary: AppColors.white,
            surface: AppColors.ivoryCream,
            onSurface: AppColors.inkBlack,
          ),
          datePickerTheme: DatePickerThemeData(
            backgroundColor: AppColors.ivoryCream,
            headerBackgroundColor: AppColors.primary,
            headerForegroundColor: AppColors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
            todayBorder: const BorderSide(color: AppColors.antiqueGold),
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) {
      reserveDate = picked;
      update();
    }
  }

  void goToReserveTab() => tabController.animateTo(2);

  void downloadMenu() =>
      CustomSnackbars.showInfo(message: 'Full menu download coming soon');

  /// Reserves a table (`POST /dining-venues/{uuid}/table-reservations`,
  /// tier-3a). A demo venue (no uuid) just confirms locally; a guest with no
  /// booking is prompted to book first. The backend picks the table — we send
  /// only date/time/party size.
  Future<void> confirmReservation() async {
    if (restaurant.uuid.isEmpty) {
      CustomSnackbars.showSuccess(
        message: AppTranslations.tableReservedFor('$guests · $timeSlot'),
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
        'date': reserveDate.formatApiDate(),
        'time': _toTime24h(timeSlot),
        'guest_count': guests,
        if (specialRequests.text.trim().isNotEmpty)
          'special_request': specialRequests.text.trim(),
      },
      showErrorDialog: false,
    );
    if (isClosed) return;
    if (res.statusCode == 201 && res.data != null) {
      final booking = ServiceBooking.fromJson(res.data!);
      final label = booking.label.isNotEmpty ? booking.label : '$guests';
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
