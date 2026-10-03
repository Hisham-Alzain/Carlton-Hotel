import 'package:carlton/controllers/booking/booking_flow_controller.dart';
import 'package:carlton/controllers/home/home_controller.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Bottom-nav shell state: which tab is active plus the [PageController] that
/// hosts the five tabs.
///
/// Registered **permanent** (see MainBinding), not route-scoped. Signing in
/// calls `Get.offAllNamed(Routes.main)` while a `/main` route is already on the
/// stack: the new MainView resolves this controller before the old route is
/// disposed, then the old route's disposal deleted it — leaving the visible
/// PageView holding a disposed controller and every later `Get.find` returning
/// a fresh, unattached one ("PageController is not attached to a PageView" on
/// the next tab switch). One long-lived instance, reset on each entry via
/// [resetTo], avoids that.
class MainController extends GetxController {
  /// Nav order: Home 0 · Stays 1 · Book 2 · Services 3 · Account 4. Opens on
  /// Services — the only fully built tab — unless a starting index is passed
  /// via `Get.arguments`.
  final RxInt currentIndex = 0.obs;
  late final PageController pageController;

  @override
  void onInit() {
    super.onInit();
    if (Get.arguments is int) currentIndex.value = Get.arguments as int;
    pageController = PageController(initialPage: currentIndex.value);
  }

  /// Called by MainBinding each time a `/main` route is pushed: start that
  /// shell on [index]. The new PageView attaches after this frame, so the jump
  /// is repeated once it has.
  void resetTo(int index) {
    currentIndex.value = index;
    if (pageController.hasClients) pageController.jumpToPage(index);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (pageController.hasClients) pageController.jumpToPage(index);
    });
  }

  void changeTab(int index) {
    if (index == currentIndex.value) return;
    currentIndex.value = index;
    if (pageController.hasClients) pageController.jumpToPage(index);
    // The Home tab's hero video should only decode while it's on screen.
    // isRegistered guards the case where the Home tab was never visited.
    if (Get.isRegistered<HomeController>()) {
      Get.find<HomeController>().setTabVisible(index == 0);
    }
    // Book (tab 2) is a live plan editor — start every visit from a fresh
    // draft, matching the old "Start Booking" behavior.
    if (index == 2) Get.find<BookingFlowController>().reset();
  }

  @override
  void onClose() {
    pageController.dispose();
    super.onClose();
  }
}
