import 'package:carlton/controllers/home/home_controller.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Bottom-nav shell state: which tab is active plus the [PageController] that
/// hosts the five tabs. Registered `fenix` so the shell and its tab state
/// survive being popped and rebuilt (e.g. returning from a full-screen route
/// pushed over it).
class MainController extends GetxController {
  /// Nav order: Home 0 · Stays 1 · Book 2 · Services 3 · Account 4. Opens on
  /// Services — the only fully built tab — unless a starting index is passed
  /// via `Get.arguments`.
  int currentIndex = 3;
  late final PageController pageController;

  @override
  void onInit() {
    super.onInit();
    if (Get.arguments is int) currentIndex = Get.arguments as int;
    pageController = PageController(initialPage: currentIndex);
  }

  void changeTab(int index) {
    if (index == currentIndex) return;
    currentIndex = index;
    pageController.jumpToPage(index);
    // The Home tab's hero video should only decode while it's on screen.
    // isRegistered guards the case where the Home tab was never visited.
    if (Get.isRegistered<HomeController>()) {
      Get.find<HomeController>().setTabVisible(index == 0);
    }
    update();
  }

  @override
  void onClose() {
    pageController.dispose();
    super.onClose();
  }
}
