import 'package:carlton/controllers/home/home_controller.dart';
import 'package:get/get.dart';

/// Figma node 2056:448 (no longer present in the file, which now covers only
/// Home + Check-In) — a brief success beat with no button; it auto-
/// advances back into Services, clearing the whole auth sub-stack (Sign In /
/// Create Profile / Phone Entry / OTP) since that flow is now an on-demand
/// action launched from Services, not the app's entry point.
class WelcomeBackController extends GetxController {
  static const _displayDuration = Duration(milliseconds: 1600);

  @override
  void onReady() {
    super.onReady();
    Future.delayed(_displayDuration, () {
      if (isClosed) return;
      // The landing point for *both* authenticated paths — OTP sign-in and
      // booking-code lookup end here — so this is where the account's
      // reservation decides which Home the guest gets.
      HomeController.restoreAndGoHome();
    });
  }
}
