import 'package:carlton/components/sheets/cancel_reservation_sheet.dart';
import 'package:carlton/components/sheets/receipt_sheet.dart';
import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_filled_button.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Owns the My Stays tabs (Active / Upcoming / Past) and the receipt + cancel
/// sheet flows. Demo-backed: lists come from [DemoData]; cancelling mutates the
/// in-memory upcoming list. Registered in `MainBinding` since Stays is a tab.
class StaysController extends GetxController
    with GetSingleTickerProviderStateMixin {
  late final TabController tabController;

  final Stay? active = DemoData.activeStay();
  final List<Stay> upcoming = DemoData.upcomingStays();
  final List<Stay> past = DemoData.pastStays();

  @override
  void onInit() {
    super.onInit();
    // Open on Upcoming — the tab with the actionable reservation.
    tabController = TabController(length: 3, vsync: this, initialIndex: 1);
  }

  void showReceipt(Stay stay) {
    final receipt = stay.receipt;
    if (receipt == null) return;

    CustomBottomSheet.show<void>(
      title: 'Receipt',
      subtitle: '${stay.roomName} · ${receipt.dateLabel}',
      child: ReceiptSheet(receipt: receipt),
      actions: CustomFilledButton(
        width: double.infinity,
        backgroundColor: AppColors.lagoonTeal,
        onPressed: () {
          Get.back();
          CustomSnackbars.showInfo(message: 'Receipt download coming soon');
        },
        child: const Text('Download PDF Receipt'),
      ),
    );
  }

  void requestCancel(Stay stay) {
    CustomBottomSheet.show<void>(
      child: CancelReservationSheet(stay: stay),
      actions: Column(
        mainAxisSize: MainAxisSize.min,
        spacing: 10,
        children: [
          CustomFilledButton(
            width: double.infinity,
            backgroundColor: AppColors.brickRed,
            onPressed: () {
              Get.back();
              _cancelReservation(stay);
            },
            child: const Text('Yes, Cancel Reservation'),
          ),
          CustomFilledButton(
            width: double.infinity,
            backgroundColor: AppColors.pearlCream,
            foregroundColor: AppColors.inkBlack,
            onPressed: () => Get.back(),
            child: const Text('No, Keep My Reservation'),
          ),
        ],
      ),
    );
  }

  void _cancelReservation(Stay stay) {
    upcoming.removeWhere((s) => s.id == stay.id);
    update();
    CustomSnackbars.showSuccess(message: 'Reservation cancelled');
  }

  /// Active-stay actions (demo). A real build routes these to the Services tab
  /// and a checkout flow respectively.
  void requestService() =>
      CustomSnackbars.showInfo(message: 'Opening room services…');

  void expressCheckout() =>
      CustomSnackbars.showInfo(message: 'Express checkout coming soon');

  /// "Book Again" / "Book" → start a fresh booking flow. Resets the shared
  /// draft first so a completed or abandoned attempt never leaks its guest
  /// or card details into the next one.
  void startBooking() {
    Get.find<BookingFlowController>().reset();
    // Get.toNamed(Routes.planStay);
  }

  @override
  void onClose() {
    tabController.dispose();
    super.onClose();
  }
}
