import 'package:carlton/constants/demo_data.dart';
import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/customWidgets/custom_bottom_sheet.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/models/booking_models.dart';
import 'package:carlton/routes/routes.dart';
import 'package:carlton/views/stays/cancel_sheet.dart';
import 'package:carlton/views/stays/receipt_sheet.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Owns the My Stays tabs (Active / Upcoming / Past) and the receipt + cancel
/// sheet flows. Demo-backed: lists come from [DemoData]; cancelling mutates the
/// in-memory upcoming list. Registered in `MainBinding` since Stays is a tab.
class StaysController extends GetxController
    with GetSingleTickerProviderStateMixin {
  static const tabs = ['Active', 'Upcoming', 'Past'];

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

  void showReceipt(ReceiptData receipt) {
    CustomBottomSheet.show(content: ReceiptSheet(receipt: receipt));
  }

  void requestCancel(Stay stay) {
    CustomBottomSheet.show(
      content: CancelSheet(
        stay: stay,
        onConfirm: () {
          Get.back<void>();
          _cancelReservation(stay);
        },
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
    Get.toNamed(Routes.planStay);
  }

  @override
  void onClose() {
    tabController.dispose();
    super.onClose();
  }
}
