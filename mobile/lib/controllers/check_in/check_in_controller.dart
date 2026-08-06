import 'package:carlton/services/check_in_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Drives the three-tab check-in wizard (Figma 75:463 / 75:808 / 75:928).
///
/// Deliberately NOT a TabController: a TabController owned by a type-keyed
/// singleton outlives the widget that created its ticker and crashes on
/// re-entry. The tabs here are a progress indicator driven by the Continue
/// buttons, so an RxInt + PageView is both safer and closer to the design.
class CheckInController extends GetxController {
  final CheckInService service = CheckInService.find;
  final PageController pageController = PageController();
  final TextEditingController notesController = TextEditingController();

  final RxInt activeTab = 0.obs;

  /// Highest tab reached. Completed tabs are tappable to go back; unvisited
  /// tabs are locked.
  final RxInt furthestTab = 0.obs;

  bool isTabUnlocked(int index) => index <= furthestTab.value;

  /// Moves to [index] in both directions. Going back never re-locks anything —
  /// [furthestTab] is a high-water mark, so a guest can revisit Identity and
  /// still jump forward to Room Key.
  void goToTab(int index) {
    if (index == activeTab.value) return;
    if (!isTabUnlocked(index)) return;
    activeTab.value = index;
    if (pageController.hasClients) {
      pageController.animateToPage(
        index,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    }
  }

  void advanceTo(int index) {
    if (index > furthestTab.value) furthestTab.value = index;
    goToTab(index);
  }

  void savePreferencesAndAdvance() {
    service.savePreferences(
      service.preferences.value.copyWith(notes: notesController.text),
    );
    advanceTo(2);
  }

  void completeAndExit() {
    service.completeCheckIn();
    Get.back<void>();
  }

  @override
  void onClose() {
    pageController.dispose();
    notesController.dispose();
    super.onClose();
  }
}
