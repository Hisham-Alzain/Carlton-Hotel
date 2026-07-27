import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/home_models.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Drives one restaurant detail screen: the menu/info/reserve tabs, the menu
/// category filter, the gallery, and the reservation form. The restaurant
/// arrives via `Get.arguments`; falls back to the first demo restaurant.
class RestaurantController extends GetxController {
  late final RestaurantItem restaurant;

  int tabIndex = 0;
  int categoryIndex = 0;
  int galleryIndex = 0;

  DateTime reserveDate = DateTime(2026, 8, 14);
  String timeSlot = DemoData.reserveTimeSlots[2];
  int guests = 2;
  final TextEditingController specialRequests = TextEditingController();

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    restaurant = arg is RestaurantItem ? arg : DemoData.restaurants.first;
  }

  @override
  void onClose() {
    specialRequests.dispose();
    super.onClose();
  }

  void switchTab(int index) {
    tabIndex = index;
    update();
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

  void goToReserveTab() => switchTab(2);

  void downloadMenu() =>
      CustomSnackbars.showInfo(message: 'Full menu download coming soon');

  void confirmReservation() {
    CustomSnackbars.showSuccess(
      message: 'Table reserved for $guests · $timeSlot',
    );
  }
}
