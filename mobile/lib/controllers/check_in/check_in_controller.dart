import 'package:carlton/controllers/home/home_controller.dart';
import 'package:carlton/customWidgets/custom_snackbar.dart';
import 'package:carlton/l10n/app_translations.dart';
import 'package:carlton/models/check_in/check_in_enums.dart';
import 'package:carlton/models/check_in/pre_arrival_step.dart';
import 'package:carlton/services/check_in_service.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Drives the three-tab check-in wizard (Figma 2237:4567 / 2237:4912 / 2237:5032).
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

  @override
  void onInit() {
    super.onInit();
    // The wizard shows the booking it is about to act on, so it has to be the
    // guest's own — the seeded placeholder is only what renders until this
    // lands.
    service.loadReservation();
  }

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

  final RxBool isCompleting = false.obs;

  /// Finishes the wizard. Stays put on anything but success — dropping the
  /// guest back on Home as if they were checked in would be worse than making
  /// them retry.
  ///
  /// Each outcome owes the guest something different: a refused request was
  /// already reported by ApiService, but an incomplete checklist never reached
  /// the network, so this is the only place that can say so. Returning silently
  /// there is what made the Complete button look dead.
  Future<void> completeAndExit() async {
    if (isCompleting.value) return;
    isCompleting.value = true;
    final outcome = await service.completeCheckIn();
    if (isClosed) return;
    isCompleting.value = false;

    switch (outcome) {
      case CheckInOutcome.incomplete:
        CustomSnackbars.showInfo(message: _missingStepsMessage());
        return;
      case CheckInOutcome.failed:
        return;
      case CheckInOutcome.success:
        break;
    }

    Get.back<void>();
    // Home owns the state decision (HomeController.resolveHomeState) — hand it
    // the refresh, don't set activeBooking from here. The reservation is now
    // `checked_in`, so re-resolving lands there.
    //
    // completeCheckIn's /me refresh already trips HomeController's entitlement
    // worker, which calls this too; refreshHome coalesces concurrent callers,
    // so this await joins that run rather than racing a second one.
    if (Get.isRegistered<HomeController>()) {
      await Get.find<HomeController>().refreshHome();
    }
  }

  /// Names the outstanding step so the guest knows where to go back to. Only
  /// the first is named — a checklist recital in a snackbar helps nobody.
  String _missingStepsMessage() {
    final missing = service.missingSteps;
    final label = switch (missing.firstOrNull) {
      PreArrivalStep.identity => AppTranslations.stepIdentity,
      PreArrivalStep.specialRequests => AppTranslations.stepSpecialRequests,
      PreArrivalStep.contactDetails => AppTranslations.stepContactDetails,
      // arrivalTime is not a required step, so it can never land here.
      _ => AppTranslations.stepIdentity,
    };
    return AppTranslations.finishStepsFirst(label);
  }

  @override
  void onClose() {
    pageController.dispose();
    notesController.dispose();
    super.onClose();
  }
}
